<?php

namespace App\Filament\Resources\ProductTransferSales\Pages;

use App\Filament\Resources\ProductTransferSales\ProductTransferSaleResource;
use App\Models\ProductTransfer;
use App\Support\Audit\ProductTransferSaleAuditLogger;
use Filament\Resources\Pages\ViewRecord;

class ViewProductTransferSale extends ViewRecord
{
    protected static string $resource = ProductTransferSaleResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $model = $this->getRecord();
        if ($model instanceof ProductTransfer && ProductTransferSaleAuditLogger::isSaleTransfer($model)) {
            ProductTransferSaleAuditLogger::logViewed($model);
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
