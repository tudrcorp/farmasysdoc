<?php

namespace App\Services\Inventory;

use App\Models\InventoryLotBalance;
use App\Models\Product;
use App\Support\Inventory\NearExpiryLotAlert;
use App\Support\Purchases\LotExpirationMonthYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consultas FEFO por sucursal/producto optimizadas para POS (una query por lote de productos).
 */
final class FefoLotBalanceQueryService
{
    /**
     * @param  list<int>  $productIds
     * @return array<int, NearExpiryLotAlert|null>
     */
    public function nearExpiryAlertsForProducts(int $branchId, array $productIds): array
    {
        if ($branchId <= 0 || $productIds === [] || ! config('inventory.fefo_pos_alerts_enabled', true)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $requiresExpiry = Product::query()
            ->whereIn('id', $ids)
            ->where('requires_expiry_on_purchase', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($requiresExpiry === []) {
            return array_fill_keys($ids, null);
        }

        $warningDays = (int) config('inventory.lot_near_expiry_days.warning', 60);
        $criticalDays = (int) config('inventory.lot_near_expiry_days.critical', 30);

        $orderExpr = LotExpirationMonthYear::mysqlOrderByExpression('pl.expiration_month_year');

        /** @var Collection<int, object> $rows */
        $rows = DB::table('inventory_lot_balances as ilb')
            ->join('product_lots as pl', 'pl.id', '=', 'ilb.product_lot_id')
            ->where('ilb.branch_id', $branchId)
            ->whereIn('ilb.product_id', $requiresExpiry)
            ->where('ilb.quantity_remaining', '>', 0)
            ->select([
                'ilb.product_id',
                'ilb.product_lot_id',
                'ilb.quantity_remaining',
                'pl.expiration_month_year',
                'pl.supplier_invoice_number',
            ])
            ->orderByRaw("{$orderExpr} ASC")
            ->get()
            ->groupBy('product_id');

        $result = array_fill_keys($ids, null);

        foreach ($requiresExpiry as $productId) {
            /** @var Collection<int, object>|null $productRows */
            $productRows = $rows->get($productId);
            if ($productRows === null || $productRows->isEmpty()) {
                continue;
            }

            $first = $productRows->first();
            if ($first === null) {
                continue;
            }

            $days = LotExpirationMonthYear::daysUntilExpiry((string) $first->expiration_month_year);
            if ($days === null || $days > $warningDays) {
                continue;
            }

            $severity = $days <= $criticalDays ? 'critical' : 'warning';

            $result[$productId] = new NearExpiryLotAlert(
                productLotId: (int) $first->product_lot_id,
                expirationMonthYear: (string) $first->expiration_month_year,
                quantityInLot: (float) $first->quantity_remaining,
                daysUntilExpiry: $days,
                supplierInvoiceNumber: trim((string) ($first->supplier_invoice_number ?? '')),
                severity: $severity,
            );
        }

        return $result;
    }

    /**
     * Lotes con stock en sucursal ordenados FEFO (para despacho en venta).
     *
     * @return list<InventoryLotBalance>
     */
    public function fefoBalancesWithLotsForProduct(int $branchId, int $productId): array
    {
        if ($branchId <= 0 || $productId <= 0) {
            return [];
        }

        $orderExpr = LotExpirationMonthYear::mysqlOrderByExpression('product_lots.expiration_month_year');

        return InventoryLotBalance::query()
            ->with('productLot:id,expiration_month_year,supplier_invoice_number')
            ->where('inventory_lot_balances.branch_id', $branchId)
            ->where('inventory_lot_balances.product_id', $productId)
            ->where('inventory_lot_balances.quantity_remaining', '>', 0)
            ->join('product_lots', 'product_lots.id', '=', 'inventory_lot_balances.product_lot_id')
            ->orderByRaw("{$orderExpr} ASC")
            ->select('inventory_lot_balances.*')
            ->get()
            ->all();
    }
}
