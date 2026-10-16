<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\SalesVatRetention;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesVatRetention>
 */
class SalesVatRetentionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tax = fake()->randomFloat(2, 100, 5000);

        return [
            'branch_id' => Branch::factory(),
            'customer_name' => strtoupper(fake()->company()),
            'customer_rif' => 'J-'.fake()->numerify('########').'-'.fake()->randomDigit(),
            'voucher_number' => now()->format('Ym').fake()->unique()->numerify('########'),
            'voucher_issued_on' => now()->toDateString(),
            'received_on' => now()->toDateString(),
            'invoice_number' => fake()->numerify('0000####'),
            'invoice_date' => now()->toDateString(),
            'invoice_tax_ves' => $tax,
            'retention_percent' => 75,
            'retained_amount_ves' => round($tax * 0.75, 2),
            'created_by' => 'factory',
        ];
    }
}
