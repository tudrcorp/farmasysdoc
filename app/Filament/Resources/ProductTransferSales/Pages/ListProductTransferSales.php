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
        return 'Ventas internas entre sucursales a costo. No aparecen en el listado de ventas ni en los totales de caja; se gestionan solo aquí.';
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
