<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum PurchaseStatus: string
{
    use HasSpanishLabels;

    case Draft = 'borrador';
    case Ordered = 'pedido-al-proveedor';
    case PartiallyReceived = 'recibido-parcialmente';
    case Received = 'recibido';
    case Cancelled = 'cancelado';
    /** Gerencia solicitó anulación; espera confirmación del administrador. */
    case CancellationRequested = 'solicita-anulacion';
    /** Compra anulada con reversión de inventario y registros derivados. */
    case Annulled = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Ordered => 'Pedido al proveedor',
            self::PartiallyReceived => 'Recibido parcialmente',
            self::Received => 'Recibido',
            self::Cancelled => 'Cancelado',
            self::CancellationRequested => 'Solicita anulación',
            self::Annulled => 'Anulada',
        };
    }
}
