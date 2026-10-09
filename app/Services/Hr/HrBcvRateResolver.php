<?php

namespace App\Services\Hr;

use App\Services\Dolar\DolarApiDolaresService;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Finance\BcvRate;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class HrBcvRateResolver
{
    public function __construct(
        private VenezuelaOfficialUsdVesRateClient $historicalClient,
        private DolarApiDolaresService $currentClient,
    ) {}

    public function resolveForDate(CarbonInterface|string $date, ?float $manualFallback = null): ?float
    {
        $target = $date instanceof CarbonInterface
            ? $date->copy()->startOfDay()
            : Carbon::parse((string) $date)->startOfDay();

        $historical = $this->historicalClient->rateForDate($target);
        if ($historical !== null && $historical > 0) {
            return $historical;
        }

        if ($target->isToday() || $target->isFuture()) {
            $current = $this->currentClient->getOfficialUsdToVesRate();
            if ($current !== null && $current > 0) {
                return $current;
            }
        }

        if ($manualFallback !== null && $manualFallback > 0) {
            return BcvRate::truncate($manualFallback);
        }

        return null;
    }
}
