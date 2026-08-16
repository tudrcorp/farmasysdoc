<?php

namespace App\Livewire\EmployeePortal;

use App\Models\Employee;
use App\Models\HrPayrollReceipt;
use App\Services\Hr\EmployeePortalAccess;
use App\Services\Hr\PayrollReceiptAvailability;
use App\Services\Hr\PayrollReceiptIssuer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee-portal')]
#[Title('Recibos de nómina')]
class Receipts extends Component
{
    public function employee(): Employee
    {
        $employee = app(EmployeePortalAccess::class)->employee();
        abort_unless($employee instanceof Employee, 403);

        return $employee;
    }

    /**
     * @return Collection<int, HrPayrollReceipt>
     */
    public function receipts(): Collection
    {
        $employee = $this->employee();
        $availability = app(PayrollReceiptAvailability::class);

        app(PayrollReceiptIssuer::class)->ensureAvailableForEmployee($employee);

        return $employee
            ->payrollReceipts()
            ->get()
            ->filter(fn (HrPayrollReceipt $receipt): bool => $availability->isAvailable(
                (int) $receipt->year,
                (int) $receipt->month,
            ))
            ->sortBy([
                ['year', 'desc'],
                ['month', 'desc'],
            ])
            ->values();
    }

    public function render(): View
    {
        $employee = $this->employee();
        $employee->loadMissing('branch');

        return view('livewire.employee-portal.receipts', [
            'employee' => $employee,
            'receipts' => $this->receipts(),
        ]);
    }
}
