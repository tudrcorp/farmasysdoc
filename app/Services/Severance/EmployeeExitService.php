<?php

namespace App\Services\Severance;

use App\Enums\EmployeeExitChecklistItem;
use App\Enums\EmployeeExitStatus;
use App\Models\Employee;
use App\Models\EmployeeExit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EmployeeExitService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function open(array $data, User $user): EmployeeExit
    {
        return DB::transaction(function () use ($data, $user): EmployeeExit {
            $employee = Employee::query()->lockForUpdate()->findOrFail($data['employee_id']);

            if (! $employee->is_active) {
                throw new RuntimeException('Este empleado ya está inactivo.');
            }

            $hasOpenExit = EmployeeExit::query()
                ->where('employee_id', $employee->id)
                ->where('status', EmployeeExitStatus::Open)
                ->exists();

            if ($hasOpenExit) {
                throw new RuntimeException('Este empleado ya tiene un egreso en proceso.');
            }

            return EmployeeExit::query()->create([...$data, 'created_by' => $user->id]);
        });
    }

    /**
     * @param  list<string>  $doneItems
     */
    public function updateChecklist(EmployeeExit $exit, array $doneItems): void
    {
        if (! $exit->isOpen()) {
            throw new RuntimeException('Este egreso ya está cerrado.');
        }

        $valid = array_column(EmployeeExitChecklistItem::cases(), 'value');

        $exit->update(['checklist' => array_values(array_intersect($valid, $doneItems))]);
    }

    public function cancel(EmployeeExit $exit, string $reason): void
    {
        if (! $exit->isOpen()) {
            throw new RuntimeException('Este egreso ya está cerrado.');
        }

        if ($exit->activeSettlement() !== null) {
            throw new RuntimeException('Primero anula la liquidación de este egreso.');
        }

        $exit->update(['status' => EmployeeExitStatus::Cancelled, 'cancel_reason' => $reason]);
    }
}
