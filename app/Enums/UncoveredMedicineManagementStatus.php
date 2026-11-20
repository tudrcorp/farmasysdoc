<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Estatus de la gestión del medicamento no cubierto en Tu Dr. Group.
 */
enum UncoveredMedicineManagementStatus: string
{
    use HasSpanishLabels;

    case Pending = 'PENDIENTE';
    case InProgress = 'EN GESTION';
    case Finished = 'FINALIZADO';
    case Cancelled = 'CANCELADA';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::InProgress => 'En gestión',
            self::Finished => 'Finalizado',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::InProgress => 'info',
            self::Finished => 'success',
            self::Cancelled => 'gray',
        };
    }
}
