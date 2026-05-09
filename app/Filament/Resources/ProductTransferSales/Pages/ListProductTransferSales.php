<?php

namespace App\Filament\Resources\ProductTransferSales\Pages;

use App\Filament\Resources\ProductTransferSales\ProductTransferSaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListProductTransferSales extends ListRecords
{
    protected static string $resource = ProductTransferSaleResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'Traslados de venta';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pedidos de mercancía entre sucursales asociados a una venta; seguimiento de envío, delivery y cierre.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo traslado de venta')
                ->icon(Heroicon::Plus)
                ->tooltip('Registrar solicitud de envío desde una venta (origen envía, destino recibe).')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary farmadoc-ios-action--liquid-glass',
                ]),
        ];
    }
}
