<?php

namespace App\Support\Shop;

use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use Throwable;

/**
 * Precios de la PWA: USD de catálogo y equivalente VES con la tasa BCV del día.
 */
final class ShopMoney
{
    private ?float $resolvedRate = null;

    private bool $resolved = false;

    public function __construct(private VenezuelaOfficialUsdVesRateClient $rateClient) {}

    public function rate(): ?float
    {
        if ($this->resolved) {
            return $this->resolvedRate;
        }

        try {
            $this->resolvedRate = $this->rateClient->rateForDate(now());
        } catch (Throwable) {
            $this->resolvedRate = null;
        }

        $this->resolved = true;

        return $this->resolvedRate;
    }

    public function ves(float $usd): ?float
    {
        $rate = $this->rate();

        if ($rate === null || $rate <= 0) {
            return null;
        }

        return round($usd * $rate, 2);
    }

    public function formatUsd(float $usd): string
    {
        return '$'.number_format($usd, 2, '.', ',');
    }

    public function formatVes(float $usd): ?string
    {
        $ves = $this->ves($usd);

        if ($ves === null) {
            return null;
        }

        return 'Bs. '.number_format($ves, 2, ',', '.');
    }
}
