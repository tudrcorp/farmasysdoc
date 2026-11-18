<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Movimientos del fondo de consumo (plan Flexible) de un aliado.
 */
enum PartnerFundMovementType: string
{
    use HasSpanishLabels;

    case Consumption = 'consumo';
    case Reversal = 'reverso';
    case Recharge = 'recarga';

    public function label(): string
    {
        return match ($this) {
            self::Consumption => 'Consumo',
            self::Reversal => 'Devolución por anulación',
            self::Recharge => 'Recarga',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Consumption => 'danger',
            self::Reversal => 'info',
            self::Recharge => 'success',
        };
    }
}
