<?php

namespace App\Filament\Resources\ProductTransferSales\Pages;

use App\Filament\Resources\ProductTransferSales\ProductTransferSaleResource;
use Filament\Resources\Pages\ViewRecord;

class ViewProductTransferSale extends ViewRecord
{
    protected static string $resource = ProductTransferSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
