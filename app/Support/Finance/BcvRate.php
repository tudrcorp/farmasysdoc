<?php

namespace App\Support\Finance;

/**
 * Regla única de la tasa BCV (Bs por USD) en todo el sistema: dos decimales cortados, sin redondear
 * (875,6599 → 875,65). Se aplica en el origen (API y tasas manuales) para que todos los cálculos
 * usen exactamente la misma tasa que se muestra.
 */
final class BcvRate
{
    public static function truncate(float $rate): float
    {
        $plain = sprintf('%.8F', $rate);
        $negative = str_starts_with($plain, '-');
        $plain = ltrim($plain, '-');
        [$whole, $fraction] = array_pad(explode('.', $plain, 2), 2, '');
        $cents = substr(str_pad($fraction, 2, '0'), 0, 2);

        return (float) (($negative ? '-' : '').$whole.'.'.$cents);
    }

    public static function truncateOrNull(mixed $rate): ?float
    {
        if (! is_numeric($rate) || (float) $rate <= 0) {
            return null;
        }

        return self::truncate((float) $rate);
    }

    public static function format(float $rate): string
    {
        return number_format(self::truncate($rate), 2, ',', '.');
    }
}
