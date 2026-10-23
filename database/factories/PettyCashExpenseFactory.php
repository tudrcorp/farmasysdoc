<?php

namespace Database\Factories;

use App\Enums\PettyCashCurrency;
use App\Models\PettyCashExpense;
use App\Models\PettyCashFund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PettyCashExpense>
 */
class PettyCashExpenseFactory extends Factory
{
    protected $model = PettyCashExpense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amountUsd = fake()->randomFloat(2, 5, 40);
        $rate = 36.5;

        return [
            'petty_cash_fund_id' => PettyCashFund::factory()->approved(),
            'spent_at' => now(),
            'description' => fake()->sentence(6),
            'currency' => PettyCashCurrency::Usd,
            'amount' => $amountUsd,
            'amount_usd' => $amountUsd,
            'amount_ves' => round($amountUsd * $rate, 2),
            'bcv_ves_per_usd' => $rate,
            'receipt_path' => 'petty-cash/expense-receipts/ticket.jpg',
            'registered_by' => User::factory(),
        ];
    }
}
