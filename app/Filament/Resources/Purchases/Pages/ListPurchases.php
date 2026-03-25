<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Resources\Purchases\PurchaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPurchases extends ListRecords
{
    protected static string $resource = PurchaseResource::class;

    protected static ?string $title = 'Listado de Compras';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva Compra')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
        ];
    }
}
