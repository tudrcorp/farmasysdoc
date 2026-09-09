<?php

namespace App\Support\Cash;

final class PhysicalCashBoxCloseVariance
{
    public const Balanced = 'balanced';

    public const Surplus = 'surplus';

    public const Shortage = 'shortage';

    public static function isMismatch(float $difference): bool
    {
        return abs($difference) >= 0.01;
    }

    public static function status(float $difference): string
    {
        if (! self::isMismatch($difference)) {
            return self::Balanced;
        }

        return $difference > 0 ? self::Surplus : self::Shortage;
    }

    public static function statusLabel(float $difference): string
    {
        return match (self::status($difference)) {
            self::Surplus => 'Sobrante',
            self::Shortage => 'Faltante',
            default => 'Cuadrado',
        };
    }

    public static function isAlertStatus(string $status): bool
    {
        return in_array($status, [self::Surplus, self::Shortage], true);
    }
}
