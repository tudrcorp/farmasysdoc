<?php

namespace Database\Factories;

use App\Models\AccountsPayable;
use App\Models\Purchase;
use App\Support\Finance\AccountsPayableStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountsPayable>
 */
class AccountsPayableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $usd = fake()->randomFloat(2, 20, 500);

        return [
            'purchase_id' => Purchase::factory(),
            'branch_id' => fn (array $attributes): int => (int) Purchase::query()->findOrFail($attributes['purchase_id'])->branch_id,
            'status' => AccountsPayableStatus::POR_PAGAR,
            'issued_at' => now()->subDays(25)->toDateString(),
            'due_at' => now()->addDays(5)->toDateString(),
            'supplier_invoice_number' => fake()->unique()->numerify('########'),
            'supplier_control_number' => fake()->numerify('00-########'),
            'supplier_tax_id' => 'J'.fake()->numerify('#########'),
            'supplier_name' => fake()->company(),
            'purchase_total_usd' => $usd,
            'remaining_principal_usd' => $usd,
            'purchase_total_ves_at_issue' => $usd * 400,
            'original_balance_ves' => $usd * 400,
            'current_balance_ves' => $usd * 420,
        ];
    }

    public function dueInDays(int $days): static
    {
        return $this->state(fn (): array => ['due_at' => now()->addDays($days)->toDateString()]);
    }
}
