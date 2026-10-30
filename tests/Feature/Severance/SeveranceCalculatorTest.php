<?php

use App\Enums\EmployeeExitReason;
use App\Services\Severance\SeveranceCalculator;
use Carbon\CarbonImmutable;

function severanceAmounts(string $hired, string $last, EmployeeExitReason $reason, array $inputs = []): array
{
    $result = (new SeveranceCalculator)->calculate(
        CarbonImmutable::parse($hired),
        CarbonImmutable::parse($last),
        300,
        $reason,
        ['utilities_days_per_year' => 30, 'vacation_base_days' => 15, 'vacation_max_days' => 30, 'vacation_bonus_base_days' => 15, 'vacation_bonus_max_days' => 30],
        [...['guarantee_accrued_usd' => 0, 'accrued_interest_usd' => 0, 'advances_usd' => 0, 'utilities_paid_usd' => 0, 'pending_vacation_days' => 0, 'pending_bonus_days' => 0, 'unpaid_salary_days' => 0, 'loans_usd' => 0], ...$inputs],
    );

    return collect($result['lines'])->pluck('amount_usd', 'concept')->all();
}

it('pays 30 days per year without counting a fraction of exactly 6 months', function (): void {
    $amounts = severanceAmounts('2023-01-10', '2025-07-09', EmployeeExitReason::Resignation);

    expect($amounts['severance'])->toBe(678.34)
        ->and($amounts['vacation_fraction'])->toBe(85.0)
        ->and($amounts['bonus_fraction'])->toBe(85.0)
        ->and($amounts['utilities_fraction'])->toBe(150.0)
        ->and($amounts)->not->toHaveKey('indemnity');
});

it('counts a fraction above 6 months as a full year', function (): void {
    expect(severanceAmounts('2023-01-10', '2025-08-09', EmployeeExitReason::Resignation)['severance'])->toBe(1017.5);
});

it('pays 5 days per month or fraction under 3 months of service', function (): void {
    expect(severanceAmounts('2025-05-01', '2025-06-15', EmployeeExitReason::Resignation)['severance'])->toBe(112.5);
});

it('uses the accrued guarantee when it is higher', function (): void {
    expect(severanceAmounts('2023-01-10', '2025-07-09', EmployeeExitReason::Resignation, ['guarantee_accrued_usd' => 700])['severance'])->toBe(700.0);
});

it('doubles severance only on unjustified dismissal', function (): void {
    $amounts = severanceAmounts('2023-01-10', '2025-07-09', EmployeeExitReason::UnjustifiedDismissal);

    expect($amounts['indemnity'])->toBe($amounts['severance']);
});
