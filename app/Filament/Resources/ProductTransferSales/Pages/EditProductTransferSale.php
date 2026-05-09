<?php

namespace App\Filament\Resources\ProductTransferSales\Pages;

use App\Filament\Resources\ProductTransferSales\ProductTransferSaleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProductTransferSale extends EditRecord
{
    protected static string $resource = ProductTransferSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
