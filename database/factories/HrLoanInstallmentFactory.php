<?php

namespace Database\Factories;

use App\Models\HrLoan;
use App\Models\HrLoanInstallment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HrLoanInstallment>
 */
class HrLoanInstallmentFactory extends Factory
{
    protected $model = HrLoanInstallment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hr_loan_id' => HrLoan::factory(),
            'number' => 1,
            'amount_usd' => fake()->randomFloat(2, 25, 100),
            'period_date' => null,
            'payroll_line_id' => null,
            'paid_at' => null,
        ];
    }
}
