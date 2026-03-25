<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\Actions\CashRegisterAction;
use App\Filament\Resources\Sales\SaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    protected static ?string $title = 'Listado de Ventas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar Venta Directa')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
            CashRegisterAction::make()
            ->action(function (array $data): void {
                dd($data);
            }),
        ];
    }
}
