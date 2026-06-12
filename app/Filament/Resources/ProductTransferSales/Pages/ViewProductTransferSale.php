<?php

namespace App\Filament\Resources\ProductTransferSales\Pages;

use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use App\Filament\Resources\ProductTransferSales\ProductTransferSaleResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\ProductTransfer;
use App\Support\Audit\ProductTransferSaleAuditLogger;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

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
        return [
            Action::make('openTransferSale')
                ->label('Ver venta interna por traslado')
                ->icon(Heroicon::Banknotes)
                ->color('gray')
                ->url(function (): ?string {
                    $record = $this->getRecord();
                    if (! $record instanceof ProductTransfer || $record->sale_id === null) {
                        return null;
                    }

                    return SaleResource::getUrl('view', ['record' => $record->sale_id], isAbsolute: false);
                })
                ->visible(fn (): bool => $this->getRecord() instanceof ProductTransfer
                    && $this->getRecord()->sale_id !== null),
            ProductTransferResource::markCompletedAction(),
            ProductTransferResource::adminChangeStatusAction(),
        ];
    }
}
