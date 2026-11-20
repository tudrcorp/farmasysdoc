<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Estados del pedido (semáforo): pendiente → en proceso → finalizado.
 */
enum OrderStatus: string
{
    use HasSpanishLabels;

    case Pending = 'pendiente';
    case Accepted = 'aceptado';
    case Shipped = 'enviado';
    case InProgress = 'en-proceso';
    case Completed = 'finalizado';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Accepted => 'Aceptado',
            self::Shipped => 'Enviado',
            self::InProgress => 'En proceso',
            self::Completed => 'Finalizado',
        };
    }

    /**
     * Color de badge Filament alineado al semáforo: rojo, amarillo, verde.
     */
    public function filamentColor(): string
    {
        return match ($this) {
            self::Pending => 'danger',
            self::Accepted => 'info',
            self::Shipped => 'success',
            self::InProgress => 'warning',
            self::Completed => 'success',
        };
    }
}
