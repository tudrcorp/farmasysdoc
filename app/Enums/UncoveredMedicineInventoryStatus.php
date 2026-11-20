<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Estado del medicamento en el inventario de IntegraCorp, calculado por Tu Dr. Group al consultar.
 */
enum UncoveredMedicineInventoryStatus: string
{
    use HasSpanishLabels;

    case Available = 'DISPONIBLE';
    case Minimum = 'EXISTENCIA_MINIMA';
    case Missing = 'NO_EXISTE';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Minimum => 'Existencia mínima',
            self::Missing => 'No existe',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Minimum => 'warning',
            self::Missing => 'danger',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Available => 'Hay existencia por encima del stock mínimo.',
            self::Minimum => 'Hay existencia, pero está en el stock mínimo o por debajo.',
            self::Missing => 'No está en el inventario o su existencia es cero.',
        };
    }
}
