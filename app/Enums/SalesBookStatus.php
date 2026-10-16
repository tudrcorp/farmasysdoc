<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum SalesBookStatus: string
{
    use HasSpanishLabels;

    case Closed = 'cerrado';
    case Reopened = 'reabierto';

    public function label(): string
    {
        return match ($this) {
            self::Closed => 'Cerrado',
            self::Reopened => 'Reabierto (versión anulada)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Closed => 'success',
            self::Reopened => 'gray',
        };
    }
}
