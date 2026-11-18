<?php

namespace App\Support\Plans;

use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Finance\BcvRate;

/**
 * Sugiere la prima en bolívares a partir de la prima en dólares con la tasa BCV del día.
 */
final class PlanPremiumConverter
{
    public static function todayRate(): ?float
    {
        return BcvRate::truncateOrNull(
            app(VenezuelaOfficialUsdVesRateClient::class)->rateForDate(now()->startOfDay())
        );
    }

    public static function usdToVes(mixed $usd, ?float $rate = null): ?float
    {
        if (! is_numeric($usd)) {
            return null;
        }

        $rate ??= self::todayRate();

        return $rate === null ? null : round((float) $usd * $rate, 2);
    }
}
