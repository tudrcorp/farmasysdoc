<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum InventoryMovementType: string
{
    use HasSpanishLabels;

    case Initial = 'inicial';
    case Purchase = 'compra';
    case Sale = 'venta';
    case Adjustment = 'ajuste';
    case Return = 'devolución';
    case Loss = 'merma';
    case Damage = 'daño';
    case Transfer = 'transferencia';
    case StockTake = 'toma-física';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Inicial',
            self::Purchase => 'Compra',
            self::Sale => 'Venta',
            self::Adjustment => 'Ajuste',
            self::Return => 'Devolución',
            self::Loss => 'Merma',
            self::Damage => 'Daño',
            self::Transfer => 'Transferencia',
            self::StockTake => 'Toma física',
        };
    }
}
