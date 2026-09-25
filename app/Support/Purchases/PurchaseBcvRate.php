<?php

namespace App\Support\Purchases;

use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use Carbon\CarbonInterface;

/**
 * Tasa BCV de la carga de facturas: dos decimales, cortados sin redondear.
 */
final class PurchaseBcvRate
{
    public static function forInvoiceDate(CarbonInterface|string|null $invoiceDate): ?float
    {
        $rate = app(VenezuelaOfficialUsdVesRateClient::class)->rateForDate($invoiceDate);

        if ($rate === null || $rate <= 0) {
            return null;
        }

        return self::truncate($rate);
    }

    public static function truncate(float $rate): float
    {
        $plain = sprintf('%.8F', $rate);
        $negative = str_starts_with($plain, '-');
        $plain = ltrim($plain, '-');
        [$whole, $fraction] = array_pad(explode('.', $plain, 2), 2, '');
        $cents = substr(str_pad($fraction, 2, '0'), 0, 2);

        return (float) (($negative ? '-' : '').$whole.'.'.$cents);
    }

    public static function format(float $rate): string
    {
        return number_format(self::truncate($rate), 2, ',', '.');
    }
}
