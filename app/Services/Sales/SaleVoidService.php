<?php

namespace App\Services\Sales;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\InventoryMovementType;
use App\Enums\SaleStatus;
use App\Filament\Resources\Sales\Actions\CashRegisterAction;
use App\Models\AccountsReceivable;
use App\Models\FiscalDocument;
use App\Models\Inventory;
use App\Models\InventoryLotBalance;
use App\Models\InventoryMovement;
use App\Models\PhysicalCashBox;
use App\Models\PhysicalCashBoxMovement;
use App\Models\ProductLot;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Fiscal\FiscalDocumentRegistrar;
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

        if ($sale->isInternalBranchTransfer()) {
            throw ValidationException::withMessages([
                'status' => 'Las ventas internas por traslado entre sucursales no se anulan desde ventas. Gestione el traslado en Inventario → Traslados de venta.',
            ]);
        }

        $this->guardSaleIsFromToday($sale);

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

            $this->guardSaleIsFromToday($sale);
            $this->guardFiscalInvoiceBeforeVoid($sale, $actorLabel);

            $sale->loadMissing(['items']);

            $branchId = (int) $sale->branch_id;

            foreach ($sale->items as $item) {
                if (! $item instanceof SaleItem) {
                    continue;
                }

                $this->restoreInventoryForSaleItem($sale, $item, $branchId, $actorLabel);
            }

            $this->restoreLotBalancesForSale($sale, $branchId);
            $reversedCashMovements = $this->reversePhysicalCashBoxMovementsForSale($sale);

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
                    'reversed_cash_box_movements' => $reversedCashMovements,
                ],
            );

            app(FiscalDocumentRegistrar::class)->registerCreditNote($sale, $actorLabel);

            return $sale->fresh(['branch', 'client', 'items']);
        });
    }

    /**
     * Solo se anulan ventas del día en curso (hora de Caracas): los días anteriores ya están cerrados en caja y reportes.
     */
    private function guardSaleIsFromToday(Sale $sale): void
    {
        $soldAt = $sale->sold_at ?? $sale->created_at;

        if ($soldAt === null || $soldAt->copy()->timezone((string) config('app.timezone'))->isToday()) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'Solo se pueden anular ventas del día de hoy. Esta venta es del '.$soldAt->copy()->timezone((string) config('app.timezone'))->format('d/m/Y').'.',
        ]);
    }

    /**
     * Devuelve a cada lote FEFO la cantidad que la venta le descontó (los movimientos de venta guardan factura y vencimiento del lote).
     */
    private function restoreLotBalancesForSale(Sale $sale, int $branchId): void
    {
        $movements = InventoryMovement::query()
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->getKey())
            ->where('movement_type', InventoryMovementType::Sale)
            ->whereNotNull('expiry_date')
            ->get();

        foreach ($movements as $movement) {
            $quantity = round(abs((float) $movement->quantity), 3);
            if ($quantity <= 0.0001 || $movement->expiry_date === null) {
                continue;
            }

            $lot = $this->resolveLotForSaleMovement($movement, $branchId);
            if (! $lot instanceof ProductLot) {
                continue;
            }

            $balance = InventoryLotBalance::query()
                ->where('branch_id', $branchId)
                ->where('product_lot_id', $lot->getKey())
                ->lockForUpdate()
                ->first();

            if ($balance instanceof InventoryLotBalance) {
                $balance->forceFill([
                    'quantity_remaining' => round((float) $balance->quantity_remaining + $quantity, 3),
                ])->save();

                continue;
            }

            InventoryLotBalance::query()->create([
                'branch_id' => $branchId,
                'product_lot_id' => $lot->getKey(),
                'product_id' => (int) $movement->product_id,
                'quantity_remaining' => $quantity,
            ]);
        }
    }

    private function resolveLotForSaleMovement(InventoryMovement $movement, int $branchId): ?ProductLot
    {
        $invoiceNumber = trim((string) ($movement->batch_number ?? ''));

        $candidates = ProductLot::query()
            ->where('product_id', (int) $movement->product_id)
            ->where('expiration_month_year', $movement->expiry_date->format('m/Y'))
            ->when(
                $invoiceNumber !== '',
                fn ($query) => $query->where('supplier_invoice_number', $invoiceNumber),
                fn ($query) => $query->where(fn ($q) => $q->whereNull('supplier_invoice_number')->orWhere('supplier_invoice_number', '')),
            )
            ->orderByDesc('id')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $withBranchBalance = InventoryLotBalance::query()
            ->where('branch_id', $branchId)
            ->whereIn('product_lot_id', $candidates->modelKeys())
            ->value('product_lot_id');

        return $withBranchBalance !== null
            ? $candidates->firstWhere('id', (int) $withBranchBalance)
            : $candidates->first();
    }

    /**
     * Deshace el efecto de la venta en la caja física del cajero (entrada de efectivo / salida de vuelto) y elimina sus movimientos.
     * Si el turno de caja donde se registró ya se cerró, no se permite anular para no descuadrar el cierre.
     *
     * @return list<array<string, mixed>>
     */
    private function reversePhysicalCashBoxMovementsForSale(Sale $sale): array
    {
        $movements = PhysicalCashBoxMovement::query()
            ->where('sale_id', $sale->getKey())
            ->lockForUpdate()
            ->get();

        $reversed = [];

        foreach ($movements as $movement) {
            $box = PhysicalCashBox::query()
                ->whereKey($movement->physical_cash_box_id)
                ->lockForUpdate()
                ->first();

            if (! $box instanceof PhysicalCashBox) {
                $movement->delete();

                continue;
            }

            $shiftStillOpen = $box->is_open
                && ($box->opened_at === null || $movement->created_at === null || $movement->created_at->gte($box->opened_at));

            if (! $shiftStillOpen) {
                throw ValidationException::withMessages([
                    'status' => 'La caja física donde se cobró esta venta ya fue cerrada. No se puede anular sin descuadrar el cierre de caja.',
                ]);
            }

            [$usdDelta, $vesDelta] = $this->physicalCashBoxMovementDeltas($movement);

            $box->forceFill([
                'amount_usd' => round((float) $box->amount_usd - $usdDelta, 2),
                'amount_ves' => round((float) $box->amount_ves - $vesDelta, 2),
            ])->save();

            $reversed[] = [
                'physical_cash_box_id' => (int) $box->getKey(),
                'kind' => (string) $movement->kind,
                'usd_delta_reversed' => $usdDelta,
                'ves_delta_reversed' => $vesDelta,
            ];

            $movement->delete();
        }

        return $reversed;
    }

    /**
     * Mismo efecto que aplicó la caja al registrar el movimiento.
     *
     * @return array{0: float, 1: float} [usd, ves]
     */
    private function physicalCashBoxMovementDeltas(PhysicalCashBoxMovement $movement): array
    {
        if (in_array($movement->kind, [CashRegisterAction::MIXED_EFECTIVO_VES_VUELTO_KIND, CashRegisterAction::EFECTIVO_VES_VUELTO_KIND], true)) {
            $meta = is_array($movement->meta) ? $movement->meta : [];

            return [0.0, round(max(0.0, (float) ($meta['net_ves_to_drawer'] ?? 0)), 2)];
        }

        return [
            round(max(0.0, (float) $movement->client_bill_usd) - max(0.0, (float) $movement->drawer_out_usd), 2),
            -1 * round(max(0.0, (float) ($movement->final_change_ves ?? 0)), 2),
        ];
    }

    /**
     * Una factura que la máquina fiscal aún puede imprimir no debe quedar viva tras anular:
     * si está pendiente se anula en cola; si el agente ya la tomó, se bloquea la anulación.
     */
    private function guardFiscalInvoiceBeforeVoid(Sale $sale, string $actorLabel): void
    {
        $invoice = FiscalDocument::query()
            ->where('sale_id', $sale->getKey())
            ->where('type', FiscalDocumentType::Invoice)
            ->lockForUpdate()
            ->first();

        if (! $invoice instanceof FiscalDocument) {
            return;
        }

        if ($invoice->simulation) {
            if (! $invoice->status->isFinal()) {
                $invoice->forceFill(['status' => FiscalDocumentStatus::Cancelled, 'resolved_by' => $actorLabel])->save();
            }

            return;
        }

        if (in_array($invoice->status, [FiscalDocumentStatus::Pending, FiscalDocumentStatus::Failed], true)) {
            $invoice->forceFill([
                'status' => FiscalDocumentStatus::Cancelled,
                'resolved_by' => $actorLabel,
            ])->save();

            return;
        }

        if ($invoice->status === FiscalDocumentStatus::Printed || $invoice->status === FiscalDocumentStatus::Cancelled) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'La factura fiscal de esta venta está en proceso o requiere revisión ('.$invoice->status->label()
                .'). Resuélvala en «Documentos fiscales» antes de anular.',
        ]);
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
