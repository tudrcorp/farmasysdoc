<?php

namespace App\Filament\Resources\Inventories\Pages;

use App\Filament\Resources\Inventories\InventoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListInventories extends ListRecords
{
    protected static string $resource = InventoryResource::class;

    protected static ?string $title = 'Listado de Inventarios';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Inventario')
                ->icon(Heroicon::Plus)
                ->color('primary'),
        ];
    }
}
