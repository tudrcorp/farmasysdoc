<?php

namespace App\Filament\BusinessPartners\Resources\OrderServices\Pages;

use App\Filament\BusinessPartners\Resources\OrderServices\OrderServiceResource;
use Filament\Resources\Pages\ListRecords;

class ListOrderServices extends ListRecords
{
    protected static string $resource = OrderServiceResource::class;

    protected static ?string $title = 'Órdenes de servicio';
}
