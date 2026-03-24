<?php

namespace App\Filament\Resources\InventoryMovements\Pages;

use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInventoryMovement extends ViewRecord
{
    protected static string $resource = InventoryMovementResource::class;

    protected static ?string $title = 'Detalle del movimiento de inventario';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
