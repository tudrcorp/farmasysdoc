<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Estado de una venta por convenio (nota de entrega).
 */
enum PartnerAgreementSaleStatus: string
{
    use HasSpanishLabels;

    case Completed = 'completada';
    case Cancelled = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completada',
            self::Cancelled => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }
}
