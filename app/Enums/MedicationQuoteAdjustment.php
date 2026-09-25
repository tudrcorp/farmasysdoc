<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum MedicationQuoteAdjustment: string
{
    use HasSpanishLabels;

    case None = 'none';
    case Discount = 'discount';
    case Increase = 'increase';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Sin ajuste',
            self::Discount => 'Descuento',
            self::Increase => 'Aumento',
        };
    }
}
