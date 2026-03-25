<?php

namespace App\Filament\Resources\InventoryMovements\Pages;

use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryMovements extends ListRecords
{
    protected static string $resource = InventoryMovementResource::class;

    protected static ?string $title = 'Movimientos de Inventario';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
