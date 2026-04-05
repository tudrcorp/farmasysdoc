<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Medio de pago cuando el pedido aliado es de contado.
 */
enum OrderPartnerCashPaymentMethod: string
{
    use HasSpanishLabels;

    case PagoMovil = 'pago_movil';
    case Zelle = 'zelle';
    case Transferencia = 'transferencia';

    public function label(): string
    {
        return match ($this) {
            self::PagoMovil => 'Pago móvil',
            self::Zelle => 'Zelle',
            self::Transferencia => 'Transferencia bancaria',
        };
    }
}
