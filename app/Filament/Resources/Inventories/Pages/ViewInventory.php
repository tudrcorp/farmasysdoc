<?php

namespace App\Filament\Resources\Inventories\Pages;

use App\Filament\Resources\Inventories\InventoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInventory extends ViewRecord
{
    protected static string $resource = InventoryResource::class;

    protected static ?string $title = 'Detalle del Producto en Inventario';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
