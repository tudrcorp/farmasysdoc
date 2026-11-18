<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Origen de una venta en pausa: la pausó un cajero o la preparó un analista desde WhatsApp.
 */
enum PosHeldSaleSource: string
{
    use HasSpanishLabels;

    case Pos = 'pos';
    case Whatsapp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Pos => 'Caja',
            self::Whatsapp => 'Pedido por WhatsApp',
        };
    }
}
