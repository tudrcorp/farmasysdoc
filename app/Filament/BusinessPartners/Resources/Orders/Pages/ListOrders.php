<?php

namespace App\Filament\BusinessPartners\Resources\Orders\Pages;

use App\Filament\BusinessPartners\Resources\Orders\OrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear pedido'),
        ];
    }
}
