<?php

namespace App\Services\Purchases;

use App\Enums\InventoryMovementType;
use App\Enums\PurchaseStatus;
use App\Models\AccountsPayable;
use App\Models\InventoryAdjustment;
use App\Models\ProductLot;
use App\Models\Purchase;
use App\Models\PurchaseHistory;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\PurchaseItemInventoryReceiptService;
use App\Support\Finance\AccountsPayableStatus;
use App\Support\Inventory\InventoryAdjustmentReason;
use App\Support\Purchases\NotifyAdministratorsPurchaseCancellationRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PurchaseAnnulmentService
{
    public function __construct(
        private readonly PurchaseItemInventoryReceiptService $receiptService,
        private readonly NotifyAdministratorsPurchaseCancellationRequested $notifyAdministratorsPurchaseCancellationRequested,
    ) {}

    /**
     * Gerencia o administrador solicita revisión para anular la compra.
     */
    public function requestCancellation(Purchase $purchase, User $actor): void
    {
        if (! $actor->hasGerenciaRole() && ! $actor->isAdministrator()) {
            throw ValidationException::withMessages([
                'status' => 'Solo Gerencia o Administrador pueden solicitar la anulación de esta compra.',
            ]);
        }

        if (! $this->mayRequestCancellation($purchase)) {
            throw ValidationException::withMessages([
                'status' => 'Esta compra no admite una nueva solicitud de anulación.',
            ]);
        }

        DB::transaction(function () use ($purchase, $actor): void {
            $purchase->forceFill([
                'status' => PurchaseStatus::CancellationRequested,
                'updated_by' => $actor->email ?? $actor->name ?? 'usuario_'.$actor->getKey(),
            ])->save();
        });

        $this->notifyAdministratorsPurchaseCancellationRequested->notify($purchase, $actor);

        AuditLogger::forModel(
            $purchase,
            'purchase_cancellation_requested',
            [
                'requested_by' => $actor->email ?? $actor->name,
                'purchase_number' => $purchase->purchase_number,
            ],
        );
    }

    /**
     * Solo administrador: confirma anulación y revierte inventario y registros derivados.
     */
    public function executeAnnulment(Purchase $purchase, User $admin): void
    {
        if (! $admin->isAdministrator()) {
            throw ValidationException::withMessages([
                'status' => 'Solo un administrador puede confirmar la anulación.',
            ]);
        }

        if ($purchase->status !== PurchaseStatus::CancellationRequested) {
            throw ValidationException::withMessages([
                'status' => 'La compra no está en estado «Solicita anulación».',
            ]);
        }

        $actorLabel = $admin->email ?? $admin->name ?? 'admin_'.$admin->getKey();

        DB::transaction(function () use ($purchase, $admin, $actorLabel): void {
            $purchase->loadMissing(['items' => fn ($q) => $q->orderBy('line_number')->orderBy('id')]);

            foreach ($purchase->items as $item) {
                if (! $item instanceof PurchaseItem) {
                    continue;
                }
                $qty = (float) $item->quantity_ordered;
                if (abs($qty) < 0.0001) {
                    continue;
                }

                $notes = 'Anulación compra '.$purchase->purchase_number
                    .($item->line_number ? ' · Línea #'.$item->line_number : '');

                $movement = $this->receiptService->applyQuantityDelta(
                    $item,
                    -1 * $qty,
                    $admin,
                    InventoryMovementType::Adjustment,
                    $notes,
                );

                $item->refresh();

                InventoryAdjustment::query()->create([
                    'purchase_id' => $purchase->getKey(),
                    'branch_id' => $purchase->branch_id,
                    'product_id' => $item->product_id,
                    'inventory_id' => $item->inventory_id,
                    'inventory_movement_id' => $movement?->getKey(),
                    'quantity_delta' => -1 * abs($qty),
                    'unit_cost_snapshot' => $item->unit_cost,
                    'reason' => InventoryAdjustmentReason::PURCHASE_ANNULMENT,
                    'notes' => $notes,
                    'created_by' => $actorLabel,
                ]);
            }

            ProductLot::query()->where('purchase_id', $purchase->getKey())->delete();

            PurchaseHistory::query()->where('purchase_id', $purchase->getKey())->delete();

            $ap = AccountsPayable::query()->where('purchase_id', $purchase->getKey())->first();
            if ($ap instanceof AccountsPayable) {
                $ap->forceFill([
                    'status' => AccountsPayableStatus::ANULADA,
                    'remaining_principal_usd' => 0,
                    'current_balance_ves' => '0',
                    'notes' => trim((string) ($ap->notes ?? '')."\n[Anulada] ".now()->toDateTimeString()),
                ])->saveQuietly();
            }

            $purchase->forceFill([
                'status' => PurchaseStatus::Annulled,
                'updated_by' => $actorLabel,
            ])->save();
        });

        AuditLogger::forModel(
            $purchase,
            'purchase_annulled',
            [
                'annulled_by' => $admin->email ?? $admin->name,
                'purchase_number' => $purchase->purchase_number,
            ],
        );
    }

    public function mayRequestCancellation(Purchase $purchase): bool
    {
        if (in_array($purchase->status, [
            PurchaseStatus::Annulled,
            PurchaseStatus::Cancelled,
            PurchaseStatus::CancellationRequested,
        ], true)) {
            return false;
        }

        return $purchase->items()->exists();
    }

    public function mayOpenApprovalPage(Purchase $purchase): bool
    {
        return $purchase->status === PurchaseStatus::CancellationRequested;
    }
}
