<?php

namespace App\Filament\Resources\Hr\Loans\Pages;

use App\Enums\HrLoanStatus;
use App\Filament\Resources\Hr\Loans\HrLoanResource;
use App\Models\Employee;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateHrLoan extends CreateRecord
{
    protected static string $resource = HrLoanResource::class;

    protected static ?string $title = 'Nuevo préstamo';

    public function mount(): void
    {
        parent::mount();

        $employeeId = request()->query('employee_id');
        if (! filled($employeeId) || ! is_numeric($employeeId)) {
            return;
        }

        $employee = Employee::query()->find((int) $employeeId);
        if (! $employee instanceof Employee) {
            return;
        }

        $this->form->fill([
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $employee = Employee::query()->find($data['employee_id'] ?? null);

        $data['branch_id'] = $employee?->branch_id ?? ($data['branch_id'] ?? null);
        $data['remaining_usd'] = $data['amount_usd'] ?? 0;
        $data['requested_by'] = $user instanceof User ? $user->id : null;

        if ($user instanceof User && $user->isAdministrator()) {
            $data['status'] = HrLoanStatus::Active->value;
            $data['approved_by'] = $user->id;
            $data['approved_at'] = now();
        } else {
            $data['status'] = HrLoanStatus::PendingApproval->value;
        }

        if (($data['installment_mode'] ?? null) === 'percentage') {
            $data['fixed_installment_usd'] = null;
            $data['installments_count'] = null;
        }

        if (($data['installment_mode'] ?? null) === 'fixed') {
            $data['salary_percentage'] = null;
        }

        return $data;
    }
}
