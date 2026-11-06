<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Eventos de la bitácora de una devolución a proveedor.
 */
enum SupplierReturnEventType: string
{
    use HasSpanishLabels;

    case Created = 'registrada';
    case Updated = 'editada';
    case Approved = 'aprobada';
    case Rejected = 'rechazada';
    case Returned = 'devuelta';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Registrada',
            self::Updated => 'Editada',
            self::Approved => 'Aprobada para devolver',
            self::Rejected => 'Rechazada',
            self::Returned => 'Devuelta al proveedor',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Approved => 'info',
            self::Returned => 'success',
            self::Rejected => 'danger',
            self::Created => 'warning',
            self::Updated => 'gray',
        };
    }
}
