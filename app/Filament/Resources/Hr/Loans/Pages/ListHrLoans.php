<?php

namespace App\Filament\Resources\Hr\Loans\Pages;

use App\Filament\Resources\Hr\Loans\HrLoanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListHrLoans extends ListRecords
{
    protected static string $resource = HrLoanResource::class;

    protected static ?string $title = 'Préstamos';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo préstamo')
                ->icon(Heroicon::Plus),
        ];
    }
}
