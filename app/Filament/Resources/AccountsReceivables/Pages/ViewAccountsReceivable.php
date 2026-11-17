<?php

namespace App\Filament\Resources\AccountsReceivables\Pages;

use App\Filament\Resources\AccountsReceivables\AccountsReceivableResource;
use App\Filament\Resources\AccountsReceivables\Actions\DownloadAccountsReceivableInvoiceAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAccountsReceivable extends ViewRecord
{
    protected static string $resource = AccountsReceivableResource::class;

    protected static ?string $title = 'Detalle de cuenta por cobrar';

    protected function getHeaderActions(): array
    {
        return [
            DownloadAccountsReceivableInvoiceAction::make(),
        ];
    }
}
