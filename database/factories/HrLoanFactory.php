<?php

namespace Database\Factories;

use App\Enums\HrLoanFrequency;
use App\Enums\HrLoanInstallmentMode;
use App\Enums\HrLoanStatus;
use App\Enums\HrPayCurrencyBucket;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\HrLoan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HrLoan>
 */
class HrLoanFactory extends Factory
{
    protected $model = HrLoan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 1000);

        return [
            'employee_id' => Employee::factory(),
            'branch_id' => Branch::factory(),
            'concept' => fake()->sentence(3),
            'amount_usd' => $amount,
            'pay_currency_bucket' => HrPayCurrencyBucket::Ves,
            'remaining_usd' => $amount,
            'frequency' => HrLoanFrequency::Biweekly,
            'installment_mode' => HrLoanInstallmentMode::Fixed,
            'fixed_installment_usd' => round($amount / 4, 2),
            'installments_count' => 4,
            'salary_percentage' => null,
            'status' => HrLoanStatus::PendingApproval,
            'requested_by' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
