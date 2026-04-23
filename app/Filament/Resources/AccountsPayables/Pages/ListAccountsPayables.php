<?php

namespace App\Filament\Resources\AccountsPayables\Pages;

use App\Filament\Resources\AccountsPayables\AccountsPayableResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountsPayables extends ListRecords
{
    protected static string $resource = AccountsPayableResource::class;

    protected static ?string $title = 'Cuentas por pagar';
}
