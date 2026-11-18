<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Estado de un plan corporativo ofrecido a compañías aliadas.
 */
enum PlanStatus: string
{
    use HasSpanishLabels;

    case Draft = 'borrador';
    case Active = 'activo';
    case Inactive = 'inactivo';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Inactive => 'danger',
        };
    }
}
