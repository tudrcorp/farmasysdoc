<?php

namespace Database\Factories;

use App\Enums\FinancialFixedExpenseCategory;
use App\Enums\PettyCashCurrency;
use App\Models\Branch;
use App\Models\FinancialFixedExpense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialFixedExpense>
 */
class FinancialFixedExpenseFactory extends Factory
{
    protected $model = FinancialFixedExpense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amountUsd = fake()->randomFloat(2, 50, 500);

        return [
            'branch_id' => Branch::factory(),
            'paid_at' => now(),
            'category' => FinancialFixedExpenseCategory::Rent,
            'description' => fake()->sentence(3),
            'currency' => PettyCashCurrency::Usd,
            'amount' => $amountUsd,
            'amount_usd' => $amountUsd,
            'amount_ves' => 0,
            'bcv_ves_per_usd' => null,
            'receipt_path' => null,
            'registered_by' => User::factory(),
        ];
    }
}
