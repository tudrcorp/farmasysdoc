<?php

namespace App\Services\Sales;

use App\Enums\InventoryMovementType;
use App\Enums\SaleStatus;
use App\Models\AccountsReceivable;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\AccountsReceivableStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class SaleVoidService
{
    public function void(Sale $sale, ?User $actor = null): Sale
    {
        if ($actor instanceof User && ! $actor->canVoidSales()) {
            throw ValidationException::withMessages([
                'status' => 'No tiene permiso para anular ventas.',
            ]);
        }

        if ($sale->status !== SaleStatus::Completed) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden anular ventas en estado «Completada».',
            ]);
        }

        $actorLabel = $actor?->email
            ?? $actor?->name
            ?? 'sistema';

        return DB::transaction(function () use ($sale, $actorLabel): Sale {
            $sale = Sale::query()
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->first();

            if (! $sale instanceof Sale || $sale->status !== SaleStatus::Completed) {
                throw ValidationException::withMessages([
                    'status' => 'La venta ya no está disponible para anular.',
                ]);
            }

            $sale->loadMissing(['items']);

            $branchId = (int) $sale->branch_id;

            foreach ($sale->items as $item) {
                if (! $item instanceof SaleItem) {
                    continue;
                }

                $this->restoreInventoryForSaleItem($sale, $item, $branchId, $actorLabel);
            }

            $accountsReceivable = AccountsReceivable::query()
                ->where('sale_id', $sale->getKey())
                ->first();

            if ($accountsReceivable instanceof AccountsReceivable) {
                $accountsReceivable->forceFill([
                    'status' => AccountsReceivableStatus::CANCELADO,
                    'notes' => trim((string) ($accountsReceivable->notes ?? '')."\n[Anulada con la venta] ".now()->toDateTimeString()),
                ])->saveQuietly();
            }

            $voidNote = 'Venta anulada el '.now()->format('d/m/Y H:i').' por '.$actorLabel;

            $sale->forceFill([
                'status' => SaleStatus::Cancelled,
                'payment_status' => 'cancelled',
                'notes' => filled($sale->notes)
                    ? trim((string) $sale->notes)."\n".$voidNote
                    : $voidNote,
                'updated_by' => $actorLabel,
            ])->save();

            AuditLogger::record(
                event: 'sale_voided',
                description: 'Venta anulada · '.$sale->sale_number,
                auditableType: Sale::class,
                auditableId: $sale->getKey(),
                auditableLabel: $sale->sale_number,
                properties: [
                    'module' => 'sales',
                    'sale_number' => $sale->sale_number,
                    'voided_by' => $actorLabel,
                    'branch_id' => (int) $sale->branch_id,
                    'total' => (float) $sale->total,
                ],
            );

            return $sale->fresh(['branch', 'client', 'items']);
        });
    }

    private function restoreInventoryForSaleItem(
        Sale $sale,
        SaleItem $item,
        int $branchId,
        string $actorLabel,
    ): void {
        $qty = (float) $item->quantity;
        if ($qty <= 0.00001) {
            return;
        }

        $productId = (int) $item->product_id;
        $inventoryId = (int) ($item->inventory_id ?? 0);

        $inventory = $inventoryId > 0
            ? Inventory::query()->whereKey($inventoryId)->lockForUpdate()->first()
            : null;

        if (! $inventory instanceof Inventory) {
            $inventory = Inventory::query()
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();
        }

        if (! $inventory instanceof Inventory) {
            throw new RuntimeException(
                'No hay inventario para devolver el producto «'.($item->product_name_snapshot ?? 'producto').'».'
            );
        }

        $inventory->quantity = round((float) $inventory->quantity + $qty, 3);
        $inventory->last_movement_at = now();
        $inventory->updated_by = $actorLabel;
        $inventory->save();

        InventoryMovement::query()->create([
            'product_id' => $productId,
            'inventory_id' => $inventory->id,
            'movement_type' => InventoryMovementType::Return,
            'quantity' => abs($qty),
            'unit_cost' => (float) ($item->unit_cost ?? 0),
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'notes' => 'Anulación venta '.$sale->sale_number,
            'created_by' => $actorLabel,
        ]);
    }
}
