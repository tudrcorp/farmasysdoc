<?php

use App\Enums\EmployeeExitChecklistItem;
use App\Enums\EmployeeExitReason;
use App\Enums\EmployeeExitStatus;
use App\Enums\EmployeeSettlementStatus;
use App\Enums\HrLoanStatus;
use App\Models\Employee;
use App\Models\EmployeeExit;
use App\Models\HrLoan;
use App\Models\User;
use App\Services\Severance\EmployeeExitService;
use App\Services\Severance\EmployeeSettlementService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $this->employee = Employee::factory()->create(['hired_at' => '2023-01-10', 'monthly_salary_usd' => 300]);
    $this->exit = EmployeeExit::factory()->create([
        'employee_id' => $this->employee->id,
        'reason' => EmployeeExitReason::Resignation,
        'last_work_day' => '2025-07-09',
    ]);

    Http::fake(['*' => Http::response([], 500)]);

    $this->service = app(EmployeeSettlementService::class);
});

function completeChecklist(EmployeeExit $exit): void
{
    $exit->update(['checklist' => array_column(EmployeeExitChecklistItem::cases(), 'value')]);
}

it('does not open two exits for the same employee', function (): void {
    app(EmployeeExitService::class)->open([
        'employee_id' => $this->employee->id,
        'reason' => EmployeeExitReason::Resignation,
        'last_work_day' => '2025-07-09',
    ], $this->admin);
})->throws(RuntimeException::class, 'en proceso');

it('requires the hire date', function (): void {
    $this->employee->update(['hired_at' => null]);

    $this->service->createDraft($this->exit, [], $this->admin);
})->throws(RuntimeException::class, 'fecha de ingreso');

it('freezes the employee data in the settlement', function (): void {
    $settlement = $this->service->createDraft($this->exit, [], $this->admin);

    $this->employee->update(['monthly_salary_usd' => 900]);

    expect((float) $settlement->refresh()->monthly_salary_usd)->toBe(300.0);
});

it('does not allow a second active settlement', function (): void {
    $this->service->createDraft($this->exit, [], $this->admin);
    $this->service->createDraft($this->exit, [], $this->admin);
})->throws(RuntimeException::class, 'activa');

it('keeps the calculated amount on manual adjustments', function (): void {
    $settlement = $this->service->createDraft($this->exit, [], $this->admin);
    $line = $settlement->lines()->where('concept', 'severance')->first();

    $this->service->adjustLine($line, 700, 'Acuerdo con contabilidad', $this->admin);

    expect((float) $line->refresh()->amount_usd)->toBe(700.0)
        ->and((float) $line->calculated_usd)->toBe(678.34)
        ->and((float) $settlement->refresh()->total_assignments_usd)->toBe(1020.0);
});

it('blocks approval and payment for non administrators', function (): void {
    $settlement = $this->service->createDraft($this->exit, [], $this->admin);
    $this->service->submit($settlement);

    $this->service->approve($settlement, User::factory()->create(), 40.0);
})->throws(RuntimeException::class, 'administrador');

it('freezes the rate and does not pay without the checklist', function (): void {
    $settlement = $this->service->createDraft($this->exit, [], $this->admin);
    $this->service->submit($settlement);
    $this->service->approve($settlement, $this->admin, 40.0);

    expect((float) $settlement->refresh()->bcv_ves_per_usd)->toBe(40.0)
        ->and((float) $settlement->net_ves)->toBe(round((float) $settlement->net_usd * 40, 2));

    $this->service->pay($settlement, 'Transferencia', '123', $this->admin);
})->throws(RuntimeException::class, 'lista de salida');

it('pays once, settles loans, deactivates the employee and closes the exit', function (): void {
    $loan = HrLoan::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => HrLoanStatus::Active,
        'remaining_usd' => 100,
    ]);
    completeChecklist($this->exit);

    $settlement = $this->service->createDraft($this->exit, [], $this->admin);
    $this->service->submit($settlement);
    $this->service->approve($settlement, $this->admin, 40.0);
    $this->service->pay($settlement, 'Transferencia', '123', $this->admin);

    expect($settlement->refresh()->status)->toBe(EmployeeSettlementStatus::Paid)
        ->and($loan->refresh()->status)->toBe(HrLoanStatus::Paid)
        ->and($this->employee->refresh()->is_active)->toBeFalse()
        ->and($this->exit->refresh()->status)->toBe(EmployeeExitStatus::Closed);

    expect(fn () => $this->service->pay($settlement, 'Transferencia', '123', $this->admin))
        ->toThrow(RuntimeException::class);
});

it('refuses to pay when the loan balance changed after the calculation', function (): void {
    $loan = HrLoan::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => HrLoanStatus::Active,
        'remaining_usd' => 100,
    ]);
    completeChecklist($this->exit);

    $settlement = $this->service->createDraft($this->exit, [], $this->admin);
    $this->service->submit($settlement);
    $this->service->approve($settlement, $this->admin, 40.0);
    $loan->update(['remaining_usd' => 50]);

    $this->service->pay($settlement, 'Transferencia', '123', $this->admin);
})->throws(RuntimeException::class, 'préstamos');

it('records every salary change', function (): void {
    $this->employee->update(['monthly_salary_usd' => 400]);

    expect((float) $this->employee->salaryChanges()->latest('id')->first()->monthly_salary_usd)->toBe(400.0);
});
