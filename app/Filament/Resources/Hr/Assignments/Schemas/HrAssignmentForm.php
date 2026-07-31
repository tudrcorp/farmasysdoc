<?php

namespace App\Filament\Resources\Hr\Assignments\Schemas;

use App\Filament\Resources\Hr\Support\HrRecurringAmountForm;
use Filament\Schemas\Schema;

class HrAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(HrRecurringAmountForm::components('Monto (USD)'));
    }
}
