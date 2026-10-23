<?php

namespace App\Support\Finance;

use App\Enums\PettyCashCurrency;
use InvalidArgumentException;

final class PettyCashMoney
{
    /**
     * @return array{amount_usd: float, amount_ves: float}
     */
    public static function split(PettyCashCurrency $currency, float $amount, ?float $bcvVesPerUsd): array
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor a cero.');
        }

        if ($currency === PettyCashCurrency::Usd) {
            $ves = ($bcvVesPerUsd !== null && $bcvVesPerUsd > 0)
                ? round($amount * $bcvVesPerUsd, 2)
                : 0.0;

            return [
                'amount_usd' => $amount,
                'amount_ves' => $ves,
            ];
        }

        if ($bcvVesPerUsd === null || $bcvVesPerUsd <= 0) {
            throw new InvalidArgumentException('No hay tasa BCV disponible para convertir bolívares a dólares.');
        }

        return [
            'amount_usd' => round($amount / $bcvVesPerUsd, 2),
            'amount_ves' => $amount,
        ];
    }

    public static function formatUsd(float $amount): string
    {
        return 'US$ '.number_format(round($amount, 2), 2, ',', '.');
    }

    public static function formatVes(float $amount): string
    {
        return 'Bs. '.number_format(round($amount, 2), 2, ',', '.');
    }
}
