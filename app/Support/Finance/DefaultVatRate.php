<?php

namespace App\Support\Finance;

use App\Models\FinancialSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Tasa de IVA por defecto del sistema (pedidos, líneas de compra, etc.).
 * Prioriza el valor en base de datos; si no hay fila, usa config/orders.php.
 */
final class DefaultVatRate
{
    private const string CACHE_KEY = 'financial_settings.default_vat_rate_percent';

    private const int CACHE_TTL_SECONDS = 3600;

    public static function percent(): float
    {
        return (float) Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): float {
            $row = FinancialSetting::query()->whereKey(1)->first();
            if ($row !== null) {
                return max(0.0, min(100.0, (float) $row->default_vat_rate_percent));
            }

            return max(0.0, min(100.0, (float) config('orders.default_vat_rate_percent', 16)));
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
