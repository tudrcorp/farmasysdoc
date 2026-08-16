<?php

namespace App\Services\Hr;

use App\Enums\HrPayrollConceptApplication;
use App\Enums\HrPayrollConceptType;
use App\Models\Employee;
use App\Models\HrPayrollConcept;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PayrollLegalReceiptBuilder
{
    /**
     * @return array{
     *     worker_name: string,
     *     national_id: ?string,
     *     month: int,
     *     month_label: string,
     *     year: int,
     *     branch_name: ?string,
     *     branch_address: ?string,
     *     legal_salary_monthly_ves: float,
     *     legal_salary_biweekly_ves: float,
     *     assignments_ves: float,
     *     deductions_ves: float,
     *     total_ves: float,
     *     items: list<array{type: string, name: string, amount_ves: float}>
     * }
     */
    public function build(Employee $employee, int $year, int $month): array
    {
        $employee->loadMissing('branch');

        $monthly = round((float) ($employee->legal_salary_ves ?? 0), 2);
        $monthDate = Carbon::create($year, $month, 1);
        $monthLabel = mb_strtoupper($monthDate->locale('es')->translatedFormat('F'), 'UTF-8');

        $items = [];
        $assignments = 0.0;
        $deductions = 0.0;

        foreach ($this->legalConcepts() as $concept) {
            $amount = $this->resolveLegalAmountVes($concept, $monthly);
            $type = $concept->type === HrPayrollConceptType::Deduction ? 'deduction' : 'assignment';

            if ($type === 'assignment') {
                $assignments += $amount;
            } else {
                $deductions += $amount;
            }

            $items[] = [
                'type' => $type,
                'name' => $concept->name,
                'amount_ves' => $amount,
            ];
        }

        $assignments = round($assignments, 2);
        $deductions = round($deductions, 2);

        return [
            'worker_name' => $employee->fullName(),
            'national_id' => $employee->formattedNationalId(),
            'month' => $month,
            'month_label' => $monthLabel,
            'year' => $year,
            'branch_name' => $employee->branch?->name,
            'branch_address' => $this->branchAddress($employee),
            'legal_salary_monthly_ves' => $monthly,
            'legal_salary_biweekly_ves' => $employee->biweeklyLegalSalaryVes(),
            'assignments_ves' => $assignments,
            'deductions_ves' => $deductions,
            'total_ves' => round($monthly + $assignments - $deductions, 2),
            'items' => $items,
        ];
    }

    /**
     * @return Collection<int, HrPayrollConcept>
     */
    private function legalConcepts(): Collection
    {
        return HrPayrollConcept::query()
            ->where('is_active', true)
            ->where('application', HrPayrollConceptApplication::Legal)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    private function resolveLegalAmountVes(HrPayrollConcept $concept, float $monthlyLegal): float
    {
        if ($concept->isPercentage()) {
            return round($monthlyLegal * ((float) $concept->amount / 100), 2);
        }

        $unit = round((float) $concept->amount, 2);

        if (preg_match('/^(\d+)\s+Nro\./u', $concept->name, $matches) === 1) {
            return round(((int) $matches[1]) * $unit, 2);
        }

        return $unit;
    }

    private function branchAddress(Employee $employee): ?string
    {
        $branch = $employee->branch;
        if ($branch === null) {
            return null;
        }

        if (filled($branch->address)) {
            return $branch->address;
        }

        $parts = array_filter([
            $branch->city,
            $branch->state,
        ], fn (mixed $value): bool => filled($value));

        return $parts === [] ? null : implode(', ', $parts);
    }
}
