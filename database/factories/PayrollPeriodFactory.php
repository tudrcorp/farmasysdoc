<?php

namespace Database\Factories;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollPeriod>
 */
class PayrollPeriodFactory extends Factory
{
    protected $model = PayrollPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (int) now()->year;

        return [
            'period_date' => now()->startOfMonth()->day(15)->toDateString(),
            'year' => $year,
            'period_number' => 1,
            'bcv_ves_per_usd' => null,
            'status' => PayrollPeriodStatus::Draft,
            'total_assignments_usd' => 0,
            'total_assignments_ves' => 0,
            'total_deductions_usd' => 0,
            'total_deductions_ves' => 0,
            'total_loans_usd' => 0,
            'total_loans_ves' => 0,
            'total_payable_usd' => 0,
            'total_payable_ves' => 0,
            'calculated_at' => null,
        ];
    }
}
