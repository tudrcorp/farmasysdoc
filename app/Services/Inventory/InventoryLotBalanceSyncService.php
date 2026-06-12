<?php

namespace App\Services\Inventory;

use App\Models\InventoryLotBalance;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Support\Purchases\LotExpirationMonthYear;

/**
 * Sincroniza saldos por lote en compras: entrada al recibir mercancía, reverso al anular o editar.
 */
final class InventoryLotBalanceSyncService
{
    /**
     * Tras crear/actualizar lotes de una compra: asegura filas de balance iniciales (sin sobrescribir ventas).
     */
    public function ensureBalancesForPurchase(Purchase $purchase): void
    {
        $branchId = (int) $purchase->branch_id;
        if ($branchId <= 0) {
            return;
        }

        $purchase->loadMissing(['items.product', 'items.productLot']);

        foreach ($purchase->items as $item) {
            if (! $item instanceof PurchaseItem) {
                continue;
            }

            $this->ensureBalanceForPurchaseItem($item, $branchId);
        }
    }

    /**
     * Aplica delta de cantidad sobre el balance del lote de la línea (positivo = entrada, negativo = salida/reverso).
     */
    public function applyQuantityDelta(PurchaseItem $item, float $delta): void
    {
        if (abs($delta) < 0.0001) {
            return;
        }

        $item->loadMissing(['product', 'productLot', 'purchase']);
        $purchase = $item->purchase;
        if (! $purchase instanceof Purchase) {
            return;
        }

        $branchId = (int) $purchase->branch_id;
        if ($branchId <= 0) {
            return;
        }

        $product = $item->product;
        if (! $product instanceof Product || ! $product->requires_expiry_on_purchase) {
            return;
        }

        $lot = $item->productLot;
        if (! $lot instanceof ProductLot) {
            return;
        }

        $exp = LotExpirationMonthYear::normalize($lot->expiration_month_year);
        if ($exp === null || ! LotExpirationMonthYear::isValidFormat($exp)) {
            return;
        }

        $balance = InventoryLotBalance::query()->firstOrCreate(
            [
                'branch_id' => $branchId,
                'product_lot_id' => $lot->getKey(),
            ],
            [
                'product_id' => (int) $item->product_id,
                'quantity_remaining' => 0,
            ],
        );

        $next = round((float) $balance->quantity_remaining + $delta, 3);

        if ($next <= 0.0001) {
            $balance->delete();

            return;
        }

        $balance->forceFill([
            'product_id' => (int) $item->product_id,
            'quantity_remaining' => $next,
        ])->save();
    }

    /**
     * Elimina balances de todos los lotes de una compra (p. ej. antes de borrar lotes en anulación).
     */
    public function deleteBalancesForPurchase(Purchase $purchase): void
    {
        $lotIds = ProductLot::query()
            ->where('purchase_id', $purchase->getKey())
            ->pluck('id');

        if ($lotIds->isEmpty()) {
            return;
        }

        InventoryLotBalance::query()
            ->whereIn('product_lot_id', $lotIds)
            ->delete();
    }

    private function ensureBalanceForPurchaseItem(PurchaseItem $item, int $branchId): void
    {
        $product = $item->product;
        if (! $product instanceof Product || ! $product->requires_expiry_on_purchase) {
            return;
        }

        $lot = $item->productLot;
        if (! $lot instanceof ProductLot) {
            return;
        }

        $exp = LotExpirationMonthYear::normalize($lot->expiration_month_year);
        if ($exp === null || ! LotExpirationMonthYear::isValidFormat($exp)) {
            return;
        }

        $qty = round((float) $item->quantity_ordered, 3);
        if ($qty <= 0.0001) {
            InventoryLotBalance::query()
                ->where('branch_id', $branchId)
                ->where('product_lot_id', $lot->getKey())
                ->delete();

            return;
        }

        $existing = InventoryLotBalance::query()
            ->where('branch_id', $branchId)
            ->where('product_lot_id', $lot->getKey())
            ->first();

        if ($existing instanceof InventoryLotBalance) {
            return;
        }

        InventoryLotBalance::query()->create([
            'branch_id' => $branchId,
            'product_lot_id' => $lot->getKey(),
            'product_id' => (int) $item->product_id,
            'quantity_remaining' => $qty,
        ]);
    }
}
