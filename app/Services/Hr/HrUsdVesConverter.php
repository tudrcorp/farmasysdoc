<?php

namespace App\Services\Hr;

final class HrUsdVesConverter
{
    public static function toVes(float|string $usd, float|string $rate): float
    {
        return round((float) $usd * (float) $rate, 2);
    }

    public static function toUsd(float|string $ves, float|string $rate): float
    {
        $rate = (float) $rate;

        if ($rate <= 0) {
            return 0.0;
        }

        return round((float) $ves / $rate, 2);
    }
}
