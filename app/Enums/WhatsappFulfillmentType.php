<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum WhatsappFulfillmentType: string
{
    use HasSpanishLabels;

    case Delivery = 'delivery';
    case Pickup = 'retiro';

    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Delivery',
            self::Pickup => 'Retiro en tienda',
        };
    }
}
