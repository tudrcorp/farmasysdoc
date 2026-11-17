<?php

namespace App\Support\Finance;

use App\Models\FinancialSetting;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Decide si se retiene IVA (SENIAT) a una factura de proveedor según su fecha de factura.
 *
 * Las facturas con fecha en el período [suspendida desde, reactivada desde) no llevan retención:
 * el libro de compras guarda 0 % / 0 Bs y la cuenta por pagar queda por el 100 % de la factura.
 * Sin fecha de factura siempre se retiene (no se usa la fecha de registro en el sistema).
 */
final class VatRetentionAgentPolicy
{
    private const string CACHE_KEY = 'financial_settings.vat_retention_agent';

    private const int CACHE_TTL_SECONDS = 3600;

    public static function retainsOnInvoiceDate(DateTimeInterface|string|null $invoiceDate): bool
    {
        if (blank($invoiceDate)) {
            return true;
        }

        $window = self::window();

        if ($window['suspended_from'] === null) {
            return true;
        }

        $date = Carbon::parse($invoiceDate)->toDateString();

        if ($date < $window['suspended_from']) {
            return true;
        }

        return $window['resumed_from'] !== null && $date >= $window['resumed_from'];
    }

    /**
     * Porcentaje efectivo para la factura: 0 si cae en el período sin retención.
     */
    public static function effectivePercent(?float $supplierPercent, DateTimeInterface|string|null $invoiceDate): ?float
    {
        return self::retainsOnInvoiceDate($invoiceDate) ? $supplierPercent : 0.0;
    }

    /**
     * @return array{active: bool, suspended_from: ?string, resumed_from: ?string}
     */
    public static function window(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $default = ['active' => true, 'suspended_from' => null, 'resumed_from' => null];

            if (! Schema::hasColumn('financial_settings', 'vat_retention_agent_active')) {
                return $default;
            }

            $row = FinancialSetting::query()->whereKey(1)->first();

            if ($row === null) {
                return $default;
            }

            return [
                'active' => (bool) $row->vat_retention_agent_active,
                'suspended_from' => $row->vat_retention_suspended_from?->toDateString(),
                'resumed_from' => $row->vat_retention_resumed_from?->toDateString(),
            ];
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
