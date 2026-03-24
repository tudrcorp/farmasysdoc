<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum OrderStatus: string
{
    use HasSpanishLabels;

    case Pending = 'pendiente';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case ReadyForDispatch = 'listo-para-despacho';
    case Dispatched = 'despachado';
    case InTransit = 'en-transito';
    case Delivered = 'entregado';
    case Cancelled = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Confirmed => 'Confirmado',
            self::Preparing => 'En preparación',
            self::ReadyForDispatch => 'Listo para despacho',
            self::Dispatched => 'Despachado',
            self::InTransit => 'En tránsito',
            self::Delivered => 'Entregado',
            self::Cancelled => 'Cancelado',
        };
    }
}
