<?php

namespace App\Filament\Resources\ProductTransferSales\Pages;

use App\Filament\Resources\ProductTransferSales\ProductTransferSaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListProductTransferSales extends ListRecords
{
    protected static string $resource = ProductTransferSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear traslado de venta')
                ->icon(Heroicon::Plus),
        ];
    }
}
