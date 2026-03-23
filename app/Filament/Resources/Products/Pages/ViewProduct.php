<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Detalle del Producto';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar Producto')
                ->icon(Heroicon::Pencil)
                ->color('primary'),
        ];
    }
}
