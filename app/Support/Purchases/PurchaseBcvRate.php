<?php

namespace App\Support\Purchases;

use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Finance\BcvRate;
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
        return BcvRate::truncate($rate);
    }

    public static function format(float $rate): string
    {
        return BcvRate::format($rate);
    }
}
