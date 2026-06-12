<?php

namespace App\Services\Inventory;

use App\Enums\FefoPosAlertSeverity;
use App\Models\FefoPosAlertLog;
use App\Models\Product;
use App\Models\User;
use App\Support\Inventory\NearExpiryLotAlert;
use Illuminate\Support\Facades\Schema;

/**
 * Persiste en base de datos cada alerta FEFO emitida en la caja registradora.
 */
final class PosFefoAlertLogRegistrar
{
    public static function register(
        int $branchId,
        User $user,
        Product $product,
        NearExpiryLotAlert $alert,
    ): void {
        if ($branchId <= 0 || ! Schema::hasTable('fefo_pos_alert_logs')) {
            return;
        }

        if (self::shouldSkipDuplicate($branchId, $user->id, $product->id, $alert->productLotId)) {
            return;
        }

        FefoPosAlertLog::query()->create([
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_lot_id' => $alert->productLotId,
            'product_code' => PosInventoryStockFailureRegistrar::resolveProductCode($product),
            'product_name' => (string) $product->name,
            'expiration_month_year' => $alert->expirationMonthYear,
            'severity' => $alert->isCritical()
                ? FefoPosAlertSeverity::Critical
                : FefoPosAlertSeverity::Warning,
            'days_until_expiry' => max(0, $alert->daysUntilExpiry),
            'quantity_in_lot' => round($alert->quantityInLot, 3),
            'supplier_invoice_number' => trim($alert->supplierInvoiceNumber) !== ''
                ? trim($alert->supplierInvoiceNumber)
                : null,
            'notified_at' => now(),
        ]);
    }

    private static function shouldSkipDuplicate(
        int $branchId,
        int $userId,
        int $productId,
        int $productLotId,
    ): bool {
        $dedupeSeconds = (int) config('inventory.fefo_alert_log_dedupe_seconds', 90);

        if ($dedupeSeconds <= 0) {
            return false;
        }

        return FefoPosAlertLog::query()
            ->where('branch_id', $branchId)
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->where('product_lot_id', $productLotId)
            ->whereNull('sale_id')
            ->where('notified_at', '>=', now()->subSeconds($dedupeSeconds))
            ->exists();
    }
}
