<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\HrPayrollReceipt;
use App\Models\PayrollPeriod;
use RuntimeException;

final class PayrollReceiptIssuer
{
    public function __construct(
        private PayrollLegalReceiptBuilder $builder,
        private PayrollReceiptAvailability $availability,
    ) {}

    public function issueForPeriod(PayrollPeriod $period): void
    {
        $this->issueForMonthIfAvailable(
            (int) $period->period_date->year,
            (int) $period->period_date->month,
        );
    }

    public function issueForMonthIfAvailable(int $year, int $month): void
    {
        if (! $this->availability->isAvailable($year, $month)) {
            return;
        }

        $this->issueForMonth($year, $month);
    }

    public function issueForMonth(int $year, int $month): void
    {
        if (! $this->availability->isAvailable($year, $month)) {
            throw new RuntimeException('El recibo mensual aún no está disponible.');
        }

        $employees = Employee::query()
            ->where('is_active', true)
            ->with('branch')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        foreach ($employees as $employee) {
            $this->issueForEmployeeMonth($employee, $year, $month);
        }
    }

    public function issueForEmployeeMonth(Employee $employee, int $year, int $month): HrPayrollReceipt
    {
        if (! $this->availability->isAvailable($year, $month)) {
            throw new RuntimeException('El recibo mensual aún no está disponible.');
        }

        $payload = $this->builder->build($employee, $year, $month);

        return HrPayrollReceipt::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'year' => $year,
                'month' => $month,
            ],
            $payload,
        );
    }

    /**
     * @return list<HrPayrollReceipt>
     */
    public function ensureAvailableForEmployee(Employee $employee): array
    {
        $receipts = [];

        foreach ($this->availability->availableMonthsFor($employee) as $slot) {
            $receipts[] = $this->issueForEmployeeMonth($employee, $slot['year'], $slot['month']);
        }

        return $receipts;
    }
}
