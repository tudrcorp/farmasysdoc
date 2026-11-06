<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Estados de una devolución de mercancía recibida de más: Por revisar → Aprobada → Devuelta, o Rechazada.
 */
enum SupplierReturnStatus: string
{
    use HasSpanishLabels;

    case PendingReview = 'por-revisar';
    case Approved = 'aprobada';
    case Returned = 'devuelta';
    case Rejected = 'rechazada';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'Por revisar',
            self::Approved => 'Aprobada (por devolver)',
            self::Returned => 'Devuelta al proveedor',
            self::Rejected => 'Rechazada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingReview => 'warning',
            self::Approved => 'info',
            self::Returned => 'success',
            self::Rejected => 'danger',
        };
    }
}
