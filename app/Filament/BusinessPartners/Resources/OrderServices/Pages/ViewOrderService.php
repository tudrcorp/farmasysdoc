<?php

namespace App\Filament\BusinessPartners\Resources\OrderServices\Pages;

use App\Filament\BusinessPartners\Resources\OrderServices\OrderServiceResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOrderService extends ViewRecord
{
    protected static string $resource = OrderServiceResource::class;

    protected static ?string $title = 'Orden de servicio';
}
