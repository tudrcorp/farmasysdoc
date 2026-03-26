<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum MarketingCouponDiscountType: string
{
    use HasSpanishLabels;

    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Porcentaje',
            self::Fixed => 'Monto fijo (USD)',
        };
    }
}
