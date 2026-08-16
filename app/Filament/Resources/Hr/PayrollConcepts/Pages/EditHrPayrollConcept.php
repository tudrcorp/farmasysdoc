<?php

namespace App\Filament\Resources\Hr\PayrollConcepts\Pages;

use App\Filament\Resources\Hr\PayrollConcepts\Concerns\PersistsPayrollConceptPeriods;
use App\Filament\Resources\Hr\PayrollConcepts\HrPayrollConceptResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHrPayrollConcept extends EditRecord
{
    use PersistsPayrollConceptPeriods;

    protected static string $resource = HrPayrollConceptResource::class;

    protected static ?string $title = 'Editar concepto de nómina';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
