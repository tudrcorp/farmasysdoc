<?php

namespace App\Filament\Resources\ProductTransfers\Pages;

use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\ProductTransfer;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewProductTransfer extends ViewRecord
{
    protected static string $resource = ProductTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openTransferSale')
                ->label('Ver venta por traslado')
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
            ProductTransferResource::takeTransferAction(),
            ProductTransferResource::markCompletedAction(),
            EditAction::make()
                ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->isAdministrator()),
        ];
    }
}
