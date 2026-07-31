<?php

namespace App\Filament\Resources\Hr\Assignments\Pages;

use App\Filament\Resources\Hr\Assignments\HrAssignmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHrAssignment extends CreateRecord
{
    protected static string $resource = HrAssignmentResource::class;

    protected static ?string $title = 'Nueva asignación';

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
