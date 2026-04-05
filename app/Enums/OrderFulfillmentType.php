<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Cómo recibe el aliado el pedido (entrega a domicilio o retiro).
 */
enum OrderFulfillmentType: string
{
    use HasSpanishLabels;

    case Delivery = 'delivery';
    case Pickup = 'pickup';

    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Delivery',
            self::Pickup => 'PickUp',
        };
    }
}
