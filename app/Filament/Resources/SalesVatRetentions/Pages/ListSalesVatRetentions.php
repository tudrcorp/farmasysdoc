<?php

namespace App\Filament\Resources\SalesVatRetentions\Pages;

use App\Filament\Resources\SalesVatRetentions\SalesVatRetentionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSalesVatRetentions extends ListRecords
{
    protected static string $resource = SalesVatRetentionResource::class;

    protected static ?string $title = 'Retenciones de IVA de clientes';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar comprobante')
                ->icon(Heroicon::Plus)
                ->color('primary'),
        ];
    }
}
