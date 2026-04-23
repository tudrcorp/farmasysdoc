<?php

namespace App\Filament\Resources\InventoryAdjustments\Pages;

use App\Filament\Resources\InventoryAdjustments\InventoryAdjustmentResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListInventoryAdjustments extends ListRecords
{
    protected static string $resource = InventoryAdjustmentResource::class;

    protected static ?string $title = 'Ajustes de inventario';

    public function getHeading(): string|Htmlable
    {
        return static::$title ?? 'Ajustes de inventario';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Consulta deltas de stock por anulación de compras y otros motivos. Filtros por sucursal, motivo o rango de fechas.';
    }
}

