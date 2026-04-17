<?php

namespace App\Support\Finance;

use App\Models\FinancialSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Tasa IGTF aplicada al total de la factura (IVA + neto) cuando el cobro es efectivo en USD.
 * Prioriza {@see FinancialSetting}; si no hay columna o valor, usa config/orders.php.
 */
final class DefaultIgtfRate
{
    private const string CACHE_KEY = 'financial_settings.igtf_rate_percent';

    private const int CACHE_TTL_SECONDS = 3600;

    public static function percent(): float
    {
        return (float) Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): float {
            if (! Schema::hasColumn('financial_settings', 'igtf_rate_percent')) {
                return max(0.0, min(100.0, (float) config('orders.default_igtf_rate_percent', 3)));
            }

            $row = FinancialSetting::query()->whereKey(1)->first();
            if ($row !== null) {
                return max(0.0, min(100.0, (float) ($row->igtf_rate_percent ?? config('orders.default_igtf_rate_percent', 3))));
            }

            return max(0.0, min(100.0, (float) config('orders.default_igtf_rate_percent', 3)));
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
