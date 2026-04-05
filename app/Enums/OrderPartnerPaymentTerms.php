<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Condición de pago acordada en pedidos de compañía aliada.
 */
enum OrderPartnerPaymentTerms: string
{
    use HasSpanishLabels;

    case Cash = 'contado';
    case Credit = 'credito';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'De contado',
            self::Credit => 'Crédito',
        };
    }
}
