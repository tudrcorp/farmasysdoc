<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Estado operativo de una entrega en logística.
 */
enum DeliveryStatus: string
{
    use HasSpanishLabels;

    case Pending = 'pendiente';
    case InProgress = 'en-proceso';
    case Completed = 'completado';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::InProgress => 'En proceso',
            self::Completed => 'Completado',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::InProgress => 'warning',
            self::Completed => 'success',
        };
    }
}
