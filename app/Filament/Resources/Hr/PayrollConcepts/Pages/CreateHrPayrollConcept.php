<?php

namespace App\Filament\Resources\Hr\PayrollConcepts\Pages;

use App\Filament\Resources\Hr\PayrollConcepts\Concerns\PersistsPayrollConceptPeriods;
use App\Filament\Resources\Hr\PayrollConcepts\HrPayrollConceptResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHrPayrollConcept extends CreateRecord
{
    use PersistsPayrollConceptPeriods;

    protected static string $resource = HrPayrollConceptResource::class;

    protected static ?string $title = 'Nuevo concepto de nómina';
}
