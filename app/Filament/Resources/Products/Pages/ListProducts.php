<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\Concerns\HasFarmaadminIosProductPage;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListProducts extends ListRecords
{
    use HasFarmaadminIosProductPage;

    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Listado de Productos';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Producto')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
        ];
    }
}
