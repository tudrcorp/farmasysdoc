<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollLine>
 */
class PayrollLineFactory extends Factory
{
    protected $model = PayrollLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_period_id' => PayrollPeriod::factory(),
            'employee_id' => Employee::factory(),
            'base_salary_usd' => 150,
            'assignments_usd' => 0,
            'deductions_usd' => 0,
            'loans_usd' => 0,
            'net_usd' => 150,
            'base_salary_ves' => 0,
            'assignments_ves' => 0,
            'deductions_ves' => 0,
            'loans_ves' => 0,
            'net_ves' => 0,
            'bcv_ves_per_usd' => 36.5,
        ];
    }
}
