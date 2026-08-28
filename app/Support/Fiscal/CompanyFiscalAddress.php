<?php

namespace App\Support\Fiscal;

use App\Models\FiscalCompanySetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Domicilio fiscal de la empresa principal. Prioriza la fila en
 * {@see FiscalCompanySetting}; si está vacía, usa config/fiscal.php.
 */
final class CompanyFiscalAddress
{
    private const string CACHE_KEY = 'fiscal_company_settings.address';

    private const int CACHE_TTL_SECONDS = 3600;

    public static function line(): string
    {
        return (string) Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): string {
            if (Schema::hasTable('fiscal_company_settings') && Schema::hasColumn('fiscal_company_settings', 'address')) {
                $row = FiscalCompanySetting::query()->whereKey(1)->first();
                if ($row !== null && filled($row->address)) {
                    return trim((string) $row->address);
                }
            }

            return trim((string) config('fiscal.retention_agent.address', ''));
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
