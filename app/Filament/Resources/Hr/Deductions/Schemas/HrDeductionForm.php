<?php

namespace App\Filament\Resources\Hr\Deductions\Schemas;

use App\Filament\Resources\Hr\Support\HrRecurringAmountForm;
use Filament\Schemas\Schema;

class HrDeductionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(HrRecurringAmountForm::components('Monto a deducir (USD)'));
    }
}
