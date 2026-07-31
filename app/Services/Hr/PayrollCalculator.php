<?php

namespace App\Services\Hr;

use App\Enums\HrLoanFrequency;
use App\Enums\HrLoanInstallmentMode;
use App\Enums\HrLoanStatus;
use App\Enums\HrRecurrence;
use App\Enums\PayrollLineItemType;
use App\Enums\PayrollPeriodStatus;
use App\Models\Employee;
use App\Models\HrAssignment;
use App\Models\HrDeduction;
use App\Models\HrLoan;
use App\Models\HrLoanInstallment;
use App\Models\PayrollLine;
use App\Models\PayrollLineItem;
use App\Models\PayrollPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class PayrollCalculator
{
    public function __construct(
        private HrBcvRateResolver $rateResolver,
    ) {}

    public function calculate(PayrollPeriod $period, ?float $manualRate = null): PayrollPeriod
    {
        if ($period->status === PayrollPeriodStatus::Closed) {
            throw new RuntimeException('No se puede recalcular un periodo cerrado.');
        }

        $rate = $this->rateResolver->resolveForDate($period->period_date, $manualRate);
        if ($rate === null || $rate <= 0) {
            throw new InvalidArgumentException('No se pudo obtener la tasa BCV. Indique una tasa manual.');
        }

        return DB::transaction(function () use ($period, $rate): PayrollPeriod {
            $this->rollbackLoanEffectsForPeriod($period);

            $period->lines()->each(function (PayrollLine $line): void {
                $line->items()->delete();
                $line->delete();
            });

            $totals = [
                'assignments_usd' => 0.0,
                'deductions_usd' => 0.0,
                'loans_usd' => 0.0,
                'payable_usd' => 0.0,
            ];

            $employees = Employee::query()
                ->where('is_active', true)
                ->with(['assignments', 'deductions', 'loans'])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            foreach ($employees as $employee) {
                $lineTotals = $this->calculateEmployeeLine($period, $employee, $rate);
                $totals['assignments_usd'] += $lineTotals['assignments_usd'];
                $totals['deductions_usd'] += $lineTotals['deductions_usd'];
                $totals['loans_usd'] += $lineTotals['loans_usd'];
                $totals['payable_usd'] += $lineTotals['net_usd'];
            }

            $period->forceFill([
                'bcv_ves_per_usd' => $rate,
                'status' => PayrollPeriodStatus::Calculated,
                'total_assignments_usd' => round($totals['assignments_usd'], 2),
                'total_assignments_ves' => HrUsdVesConverter::toVes($totals['assignments_usd'], $rate),
                'total_deductions_usd' => round($totals['deductions_usd'], 2),
                'total_deductions_ves' => HrUsdVesConverter::toVes($totals['deductions_usd'], $rate),
                'total_loans_usd' => round($totals['loans_usd'], 2),
                'total_loans_ves' => HrUsdVesConverter::toVes($totals['loans_usd'], $rate),
                'total_payable_usd' => round($totals['payable_usd'], 2),
                'total_payable_ves' => HrUsdVesConverter::toVes($totals['payable_usd'], $rate),
                'calculated_at' => now(),
            ])->save();

            return $period->refresh();
        });
    }

    public function close(PayrollPeriod $period): PayrollPeriod
    {
        if ($period->status !== PayrollPeriodStatus::Calculated) {
            throw new RuntimeException('Solo se pueden cerrar periodos calculados.');
        }

        $period->forceFill([
            'status' => PayrollPeriodStatus::Closed,
        ])->save();

        return $period->refresh();
    }

    /**
     * @return array{assignments_usd: float, deductions_usd: float, loans_usd: float, net_usd: float}
     */
    private function calculateEmployeeLine(PayrollPeriod $period, Employee $employee, float $rate): array
    {
        $baseUsd = round((float) $employee->monthly_salary_usd / 2, 2);
        $periodDate = $period->period_date->toDateString();

        $assignmentItems = [];
        $assignmentsUsd = 0.0;
        foreach ($employee->assignments as $assignment) {
            if (! $this->appliesOnPeriod($assignment, $periodDate)) {
                continue;
            }
            $amount = round((float) $assignment->amount_usd, 2);
            $assignmentsUsd += $amount;
            $assignmentItems[] = [$assignment, $amount];
        }

        $deductionItems = [];
        $deductionsUsd = 0.0;
        foreach ($employee->deductions as $deduction) {
            if (! $this->appliesOnPeriod($deduction, $periodDate)) {
                continue;
            }
            $amount = round((float) $deduction->amount_usd, 2);
            $deductionsUsd += $amount;
            $deductionItems[] = [$deduction, $amount];
        }

        $loanItems = [];
        $loansUsd = 0.0;
        foreach ($employee->loans as $loan) {
            if ($loan->status !== HrLoanStatus::Active || (float) $loan->remaining_usd <= 0) {
                continue;
            }

            if (! $this->loanAppliesToPeriod($loan, $period)) {
                continue;
            }

            $amount = $this->loanInstallmentAmount($loan, $employee, $baseUsd);
            if ($amount <= 0) {
                continue;
            }

            $loansUsd += $amount;
            $loanItems[] = [$loan, $amount];
        }

        $netUsd = round($baseUsd + $assignmentsUsd - $deductionsUsd - $loansUsd, 2);

        $line = PayrollLine::query()->create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'base_salary_usd' => $baseUsd,
            'assignments_usd' => round($assignmentsUsd, 2),
            'deductions_usd' => round($deductionsUsd, 2),
            'loans_usd' => round($loansUsd, 2),
            'net_usd' => $netUsd,
            'base_salary_ves' => HrUsdVesConverter::toVes($baseUsd, $rate),
            'assignments_ves' => HrUsdVesConverter::toVes($assignmentsUsd, $rate),
            'deductions_ves' => HrUsdVesConverter::toVes($deductionsUsd, $rate),
            'loans_ves' => HrUsdVesConverter::toVes($loansUsd, $rate),
            'net_ves' => HrUsdVesConverter::toVes($netUsd, $rate),
            'bcv_ves_per_usd' => $rate,
        ]);

        $this->createItem($line, PayrollLineItemType::Base, null, 'Sueldo base quincenal', $baseUsd, $rate);

        foreach ($assignmentItems as [$assignment, $amount]) {
            $this->createItem($line, PayrollLineItemType::Assignment, $assignment, $assignment->concept, $amount, $rate);
        }

        foreach ($deductionItems as [$deduction, $amount]) {
            $this->createItem($line, PayrollLineItemType::Deduction, $deduction, $deduction->concept, $amount, $rate);
        }

        foreach ($loanItems as [$loan, $amount]) {
            $this->createItem(
                $line,
                PayrollLineItemType::Loan,
                $loan,
                $loan->concept ?: 'Descuento por préstamo',
                $amount,
                $rate,
            );
            $this->applyLoanPayment($loan, $amount, $period, $line);
        }

        return [
            'assignments_usd' => round($assignmentsUsd, 2),
            'deductions_usd' => round($deductionsUsd, 2),
            'loans_usd' => round($loansUsd, 2),
            'net_usd' => $netUsd,
        ];
    }

    private function appliesOnPeriod(HrAssignment|HrDeduction $record, string $periodDate): bool
    {
        if (! $record->is_active) {
            return false;
        }

        if ($record->recurrence === HrRecurrence::Once) {
            return $record->applies_on?->toDateString() === $periodDate;
        }

        $date = Carbon::parse($periodDate)->startOfDay();

        if ($record->starts_on !== null && $date->lt($record->starts_on->copy()->startOfDay())) {
            return false;
        }

        if ($record->ends_on !== null && $date->gt($record->ends_on->copy()->startOfDay())) {
            return false;
        }

        return true;
    }

    private function loanAppliesToPeriod(HrLoan $loan, PayrollPeriod $period): bool
    {
        if ($loan->frequency === HrLoanFrequency::Biweekly) {
            return true;
        }

        return $period->isMonthEnd();
    }

    private function loanInstallmentAmount(HrLoan $loan, Employee $employee, float $biweeklyBase): float
    {
        $remaining = round((float) $loan->remaining_usd, 2);

        if ($loan->installment_mode === HrLoanInstallmentMode::Fixed) {
            $fixed = round((float) ($loan->fixed_installment_usd ?? 0), 2);

            return min($fixed, $remaining);
        }

        $percentage = (float) ($loan->salary_percentage ?? 0);
        $base = $loan->frequency === HrLoanFrequency::Monthly
            ? (float) $employee->monthly_salary_usd
            : $biweeklyBase;

        $amount = round($base * ($percentage / 100), 2);

        return min($amount, $remaining);
    }

    private function applyLoanPayment(HrLoan $loan, float $amount, PayrollPeriod $period, PayrollLine $line): void
    {
        $remaining = round(max(0, (float) $loan->remaining_usd - $amount), 2);

        $loan->forceFill([
            'remaining_usd' => $remaining,
            'status' => $remaining <= 0 ? HrLoanStatus::Paid : HrLoanStatus::Active,
        ])->save();

        $nextNumber = (int) $loan->installments()->max('number') + 1;

        HrLoanInstallment::query()->create([
            'hr_loan_id' => $loan->id,
            'number' => $nextNumber,
            'amount_usd' => $amount,
            'period_date' => $period->period_date->toDateString(),
            'payroll_line_id' => $line->id,
            'paid_at' => now(),
        ]);
    }

    private function rollbackLoanEffectsForPeriod(PayrollPeriod $period): void
    {
        $installments = HrLoanInstallment::query()
            ->where('period_date', $period->period_date->toDateString())
            ->whereNotNull('paid_at')
            ->with('loan')
            ->get();

        foreach ($installments as $installment) {
            $loan = $installment->loan;
            if ($loan instanceof HrLoan) {
                $restored = round((float) $loan->remaining_usd + (float) $installment->amount_usd, 2);
                $loan->forceFill([
                    'remaining_usd' => min($restored, (float) $loan->amount_usd),
                    'status' => HrLoanStatus::Active,
                ])->save();
            }

            $installment->delete();
        }
    }

    private function createItem(
        PayrollLine $line,
        PayrollLineItemType $type,
        HrAssignment|HrDeduction|HrLoan|null $reference,
        string $concept,
        float $amountUsd,
        float $rate,
    ): void {
        PayrollLineItem::query()->create([
            'payroll_line_id' => $line->id,
            'type' => $type,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'concept' => $concept,
            'amount_usd' => $amountUsd,
            'amount_ves' => HrUsdVesConverter::toVes($amountUsd, $rate),
        ]);
    }
}
