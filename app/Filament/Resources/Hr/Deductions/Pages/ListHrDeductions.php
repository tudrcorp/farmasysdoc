<?php

namespace App\Filament\Resources\Hr\Deductions\Pages;

use App\Filament\Resources\Hr\Deductions\HrDeductionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListHrDeductions extends ListRecords
{
    protected static string $resource = HrDeductionResource::class;

    protected static ?string $title = 'Deducciones';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva deducción')
                ->icon(Heroicon::Plus),
        ];
    }
}
