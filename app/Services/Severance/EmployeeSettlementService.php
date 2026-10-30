<?php

namespace App\Services\Severance;

use App\Enums\EmployeeExitStatus;
use App\Enums\EmployeeSettlementLineType;
use App\Enums\EmployeeSettlementStatus;
use App\Enums\HrLoanStatus;
use App\Models\EmployeeExit;
use App\Models\EmployeeSettlement;
use App\Models\EmployeeSettlementLine;
use App\Models\HrLoan;
use App\Models\HrLoanInstallment;
use App\Models\SeveranceSetting;
use App\Models\User;
use App\Services\Hr\HrBcvRateResolver;
use App\Services\Hr\HrUsdVesConverter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Ciclo de la liquidación. Cada paso bloquea la fila, así dos personas
 * no pueden aprobar ni pagar la misma liquidación a la vez.
 */
class EmployeeSettlementService
{
    public function __construct(
        private SeveranceCalculator $calculator,
        private HrBcvRateResolver $rateResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $inputs
     */
    public function createDraft(EmployeeExit $exit, array $inputs, User $user): EmployeeSettlement
    {
        return DB::transaction(function () use ($exit, $inputs, $user): EmployeeSettlement {
            $lockedExit = EmployeeExit::query()->lockForUpdate()->findOrFail($exit->id);

            if (! $lockedExit->isOpen()) {
                throw new RuntimeException('Este egreso ya está cerrado.');
            }

            if ($lockedExit->activeSettlement() !== null) {
                throw new RuntimeException('Este egreso ya tiene una liquidación activa.');
            }

            $employee = $lockedExit->employee;

            if ($employee->hired_at === null) {
                throw new RuntimeException('Carga la fecha de ingreso del empleado antes de liquidar.');
            }

            if ((float) $employee->monthly_salary_usd <= 0) {
                throw new RuntimeException('El empleado no tiene sueldo mensual cargado.');
            }

            if ($employee->hired_at->greaterThan($lockedExit->last_work_day)) {
                throw new RuntimeException('El último día de trabajo es anterior a la fecha de ingreso.');
            }

            $settlement = new EmployeeSettlement([
                'employee_exit_id' => $lockedExit->id,
                'hired_at' => $employee->hired_at,
                'last_work_day' => $lockedExit->last_work_day,
                'monthly_salary_usd' => $employee->monthly_salary_usd,
                'parameters' => SeveranceSetting::current()->toParameters(),
                'created_by' => $user->id,
            ]);

            return $this->applyCalculation($settlement, $lockedExit, $inputs);
        });
    }

    /**
     * Recalcular descarta los ajustes manuales: los conceptos se generan de nuevo.
     *
     * @param  array<string, mixed>  $inputs
     */
    public function recalculate(EmployeeSettlement $settlement, array $inputs): EmployeeSettlement
    {
        return DB::transaction(function () use ($settlement, $inputs): EmployeeSettlement {
            $locked = $this->lock($settlement);
            $this->ensureStatus($locked, EmployeeSettlementStatus::Draft);

            return $this->applyCalculation($locked, $locked->employeeExit, $inputs);
        });
    }

    public function adjustLine(EmployeeSettlementLine $line, float $amountUsd, string $reason, User $user): void
    {
        if ($amountUsd < 0) {
            throw new InvalidArgumentException('El monto no puede ser negativo.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Indica el motivo del ajuste.');
        }

        DB::transaction(function () use ($line, $amountUsd, $reason, $user): void {
            $settlement = $this->lock($line->settlement);
            $this->ensureStatus($settlement, EmployeeSettlementStatus::Draft);

            $line->update(['amount_usd' => $amountUsd, 'adjustment_reason' => trim($reason), 'adjusted_by' => $user->id]);

            $this->refreshTotals($settlement);
        });
    }

    public function submit(EmployeeSettlement $settlement): void
    {
        DB::transaction(function () use ($settlement): void {
            $locked = $this->lock($settlement);
            $this->ensureStatus($locked, EmployeeSettlementStatus::Draft);
            $this->ensureNetIsPayable($locked);

            $locked->update(['status' => EmployeeSettlementStatus::PendingApproval]);
        });
    }

    public function returnToDraft(EmployeeSettlement $settlement, User $user): void
    {
        $this->ensureAdministrator($user);

        DB::transaction(function () use ($settlement): void {
            $locked = $this->lock($settlement);

            if (! in_array($locked->status, [EmployeeSettlementStatus::PendingApproval, EmployeeSettlementStatus::Approved], true)) {
                throw new RuntimeException('Solo se devuelve una liquidación por aprobar o aprobada.');
            }

            $locked->update([
                'status' => EmployeeSettlementStatus::Draft,
                'approved_by' => null,
                'approved_at' => null,
                'bcv_ves_per_usd' => null,
                'net_ves' => null,
            ]);
        });
    }

    public function approve(EmployeeSettlement $settlement, User $user, ?float $manualRate = null): void
    {
        $this->ensureAdministrator($user);

        $rate = $this->rateResolver->resolveForDate(now(), $manualRate);
        if ($rate === null || $rate <= 0) {
            throw new RuntimeException('No se pudo obtener la tasa BCV. Indica una tasa manual.');
        }

        DB::transaction(function () use ($settlement, $user, $rate): void {
            $locked = $this->lock($settlement);
            $this->ensureStatus($locked, EmployeeSettlementStatus::PendingApproval);
            $this->ensureNetIsPayable($locked);

            $locked->update([
                'status' => EmployeeSettlementStatus::Approved,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'bcv_ves_per_usd' => $rate,
                'net_ves' => HrUsdVesConverter::toVes((float) $locked->net_usd, $rate),
            ]);
        });
    }

    /**
     * Paga, salda los préstamos, inactiva al empleado y cierra el egreso, todo o nada.
     */
    public function pay(EmployeeSettlement $settlement, string $method, string $reference, User $user): void
    {
        $this->ensureAdministrator($user);

        DB::transaction(function () use ($settlement, $method, $reference, $user): void {
            $locked = $this->lock($settlement);
            $this->ensureStatus($locked, EmployeeSettlementStatus::Approved);

            $exit = $locked->employeeExit;
            $pending = $exit->pendingChecklistLabels();

            if ($pending !== []) {
                throw new RuntimeException('Falta completar la lista de salida: '.implode(', ', $pending).'.');
            }

            $this->settleLoans($locked, $exit);

            $locked->update([
                'status' => EmployeeSettlementStatus::Paid,
                'paid_by' => $user->id,
                'paid_at' => now(),
                'payment_method' => $method,
                'payment_reference' => $reference,
            ]);

            $exit->employee->update(['is_active' => false]);
            $exit->update(['status' => EmployeeExitStatus::Closed, 'closed_at' => now()]);
        });
    }

    public function cancel(EmployeeSettlement $settlement, string $reason, User $user): void
    {
        $this->ensureAdministrator($user);

        DB::transaction(function () use ($settlement, $reason): void {
            $locked = $this->lock($settlement);

            if (in_array($locked->status, [EmployeeSettlementStatus::Paid, EmployeeSettlementStatus::Cancelled], true)) {
                throw new RuntimeException('Una liquidación pagada o anulada no se puede anular.');
            }

            $locked->update(['status' => EmployeeSettlementStatus::Cancelled, 'cancel_reason' => $reason]);
        });
    }

    public function activeLoansBalance(EmployeeExit $exit): float
    {
        return round((float) HrLoan::query()
            ->where('employee_id', $exit->employee_id)
            ->where('status', HrLoanStatus::Active)
            ->sum('remaining_usd'), 2);
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function applyCalculation(EmployeeSettlement $settlement, EmployeeExit $exit, array $inputs): EmployeeSettlement
    {
        $values = [];
        foreach (['guarantee_accrued_usd', 'accrued_interest_usd', 'advances_usd', 'utilities_paid_usd', 'pending_vacation_days', 'pending_bonus_days', 'unpaid_salary_days'] as $field) {
            $values[$field] = round(max(0, (float) ($inputs[$field] ?? 0)), 2);
        }

        $result = $this->calculator->calculate(
            CarbonImmutable::parse($settlement->hired_at),
            CarbonImmutable::parse($settlement->last_work_day),
            (float) $settlement->monthly_salary_usd,
            $exit->reason,
            $settlement->parameters,
            [...$values, 'loans_usd' => $this->activeLoansBalance($exit)],
        );

        $settlement->fill([
            ...$values,
            'service_label' => $result['service_label'],
            'normal_daily_usd' => $result['normal_daily_usd'],
            'integral_daily_usd' => $result['integral_daily_usd'],
        ])->save();

        $settlement->lines()->delete();

        foreach ($result['lines'] as $sort => $line) {
            $settlement->lines()->create([
                ...$line,
                'calculated_usd' => $line['amount_usd'],
                'sort' => $sort,
            ]);
        }

        $this->refreshTotals($settlement);

        return $settlement;
    }

    private function refreshTotals(EmployeeSettlement $settlement): void
    {
        $lines = $settlement->lines()->get();
        $sum = fn (EmployeeSettlementLineType $type): float => round(
            (float) $lines->where('type', $type)->sum('amount_usd'),
            2,
        );

        $assignments = $sum(EmployeeSettlementLineType::Assignment);
        $deductions = $sum(EmployeeSettlementLineType::Deduction);

        $settlement->update([
            'total_assignments_usd' => $assignments,
            'total_deductions_usd' => $deductions,
            'net_usd' => round($assignments - $deductions, 2),
        ]);
    }

    /**
     * El saldo pudo cambiar en una nómina posterior al cálculo; no se paga con un monto desactualizado.
     */
    private function settleLoans(EmployeeSettlement $settlement, EmployeeExit $exit): void
    {
        $deducted = (float) $settlement->lines()->where('concept', 'loans')->sum('amount_usd');
        $calculated = (float) $settlement->lines()->where('concept', 'loans')->sum('calculated_usd');
        $balance = $this->activeLoansBalance($exit);

        if (round($calculated - $balance, 2) !== 0.0) {
            throw new RuntimeException('El saldo de préstamos cambió desde el cálculo. Devuelve la liquidación a borrador y recalcula.');
        }

        if ($deducted <= 0) {
            return;
        }

        $remainingToApply = $deducted;

        HrLoan::query()
            ->where('employee_id', $exit->employee_id)
            ->where('status', HrLoanStatus::Active)
            ->lockForUpdate()
            ->oldest('id')
            ->get()
            ->each(function (HrLoan $loan) use (&$remainingToApply, $settlement): void {
                $amount = round(min($remainingToApply, (float) $loan->remaining_usd), 2);
                if ($amount <= 0) {
                    return;
                }

                $remainingToApply = round($remainingToApply - $amount, 2);
                $remaining = round((float) $loan->remaining_usd - $amount, 2);

                $loan->forceFill([
                    'remaining_usd' => $remaining,
                    'status' => $remaining <= 0 ? HrLoanStatus::Paid : HrLoanStatus::Active,
                ])->save();

                HrLoanInstallment::query()->create([
                    'hr_loan_id' => $loan->id,
                    'number' => (int) $loan->installments()->max('number') + 1,
                    'amount_usd' => $amount,
                    'period_date' => $settlement->last_work_day->toDateString(),
                    'paid_at' => now(),
                ]);
            });
    }

    private function lock(EmployeeSettlement $settlement): EmployeeSettlement
    {
        return EmployeeSettlement::query()->lockForUpdate()->findOrFail($settlement->id);
    }

    private function ensureStatus(EmployeeSettlement $settlement, EmployeeSettlementStatus $status): void
    {
        if ($settlement->status !== $status) {
            throw new RuntimeException("Esta liquidación debe estar en estado «{$status->label()}».");
        }
    }

    private function ensureNetIsPayable(EmployeeSettlement $settlement): void
    {
        if ((float) $settlement->net_usd < 0) {
            throw new RuntimeException('Las deducciones superan las asignaciones. Revisa los montos con un ajuste.');
        }
    }

    private function ensureAdministrator(User $user): void
    {
        if (! $user->isAdministrator()) {
            throw new RuntimeException('Solo un administrador puede hacer esta acción.');
        }
    }
}
