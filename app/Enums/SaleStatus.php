<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum SaleStatus: string
{
    use HasSpanishLabels;

    case Draft = 'borrador';
    case Completed = 'completada';
    case Cancelled = 'cancelada';
    case Refunded = 'reembolsada';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Completed => 'Completada',
            self::Cancelled => 'Cancelada',
            self::Refunded => 'Reembolsada',
        };
    }
}
