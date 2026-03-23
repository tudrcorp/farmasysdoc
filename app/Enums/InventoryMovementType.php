<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Initial = 'initial';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Adjustment = 'adjustment';
    case Return = 'return';
    case Loss = 'loss';
    case Damage = 'damage';
    case Transfer = 'transfer';
    case StockTake = 'stock_take';
}
