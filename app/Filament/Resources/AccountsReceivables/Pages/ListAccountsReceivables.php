<?php

namespace App\Filament\Resources\AccountsReceivables\Pages;

use App\Filament\Resources\AccountsReceivables\AccountsReceivableResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountsReceivables extends ListRecords
{
    protected static string $resource = AccountsReceivableResource::class;

    protected static ?string $title = 'Cuentas por cobrar';
}
