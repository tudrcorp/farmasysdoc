<?php

namespace App\Filament\Resources\Hr\PayrollConcepts\Pages;

use App\Filament\Resources\Hr\PayrollConcepts\HrPayrollConceptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListHrPayrollConcepts extends ListRecords
{
    protected static string $resource = HrPayrollConceptResource::class;

    protected static ?string $title = 'Conceptos de Nómina';

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'fi-hr-payroll-concepts-list-page',
            'fi-hr-ios-filters-page',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo concepto')
                ->icon(Heroicon::Plus),
        ];
    }
}
