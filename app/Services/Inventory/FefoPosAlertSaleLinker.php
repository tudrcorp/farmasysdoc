<?php

namespace App\Services\Inventory;

use App\Models\FefoPosAlertLog;
use App\Models\Sale;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula alertas FEFO pendientes con la venta completada en caja (mismo cajero y producto).
 */
final class FefoPosAlertSaleLinker
{
    /**
     * @param  array<int, float>  $qtyByProduct
     */
    public function linkSale(Sale $sale, int $branchId, int $userId, array $qtyByProduct): void
    {
        if (! Schema::hasTable('fefo_pos_alert_logs') || $qtyByProduct === []) {
            return;
        }

        $linkWindowHours = (int) config('inventory.fefo_alert_sale_link_hours', 4);
        $windowStart = now()->subHours(max(1, $linkWindowHours));

        foreach ($qtyByProduct as $productId => $quantity) {
            $productId = (int) $productId;
            if ($productId <= 0 || $quantity <= 0.0001) {
                continue;
            }

            $log = FefoPosAlertLog::query()
                ->where('branch_id', $branchId)
                ->where('user_id', $userId)
                ->where('product_id', $productId)
                ->whereNull('sale_id')
                ->where('notified_at', '>=', $windowStart)
                ->orderByDesc('notified_at')
                ->first();

            if (! $log instanceof FefoPosAlertLog) {
                continue;
            }

            $log->forceFill([
                'sale_id' => $sale->getKey(),
                'sale_number' => (string) $sale->sale_number,
                'quantity_sold' => round($quantity, 3),
                'sold_at' => $sale->sold_at ?? now(),
            ])->save();
        }
    }
}
