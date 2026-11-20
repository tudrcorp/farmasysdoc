<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Estado de la copia local de medicamentos no cubiertos de un aliado.
 */
enum UncoveredMedicineSyncStatus: string
{
    use HasSpanishLabels;

    case Idle = 'idle';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Idle => 'Sin consultar',
            self::Running => 'Actualizando',
            self::Succeeded => 'Actualizado',
            self::Failed => 'Con error',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Idle => 'gray',
            self::Running => 'info',
            self::Succeeded => 'success',
            self::Failed => 'danger',
        };
    }
}
