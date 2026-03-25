<?php

namespace App\Filament\Resources\OrderServices\Pages;

use App\Filament\Resources\OrderServices\OrderServiceResource;
use Filament\Resources\Pages\ListRecords;

class ListOrderServices extends ListRecords
{
    protected static string $resource = OrderServiceResource::class;

    protected static ?string $title = 'Listado de Ordenes de Servicio';

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
