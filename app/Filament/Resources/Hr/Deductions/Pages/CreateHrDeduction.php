<?php

namespace App\Filament\Resources\Hr\Deductions\Pages;

use App\Filament\Resources\Hr\Deductions\HrDeductionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHrDeduction extends CreateRecord
{
    protected static string $resource = HrDeductionResource::class;

    protected static ?string $title = 'Nueva deducción';

    public function mount(): void
    {
        parent::mount();

        $employeeId = request()->query('employee_id');
        if (filled($employeeId) && is_numeric($employeeId)) {
            $this->form->fill([
                'employee_id' => (int) $employeeId,
            ]);
        }
    }
}
