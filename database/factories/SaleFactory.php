<?php

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 20, 500);
        $taxTotal = round($subtotal * 0.19, 2);
        $discount = 0;
        $total = $subtotal + $taxTotal - $discount;

        return [
            'sale_number' => fake()->unique()->bothify('VTA-2026-#####'),
            'branch_id' => Branch::factory(),
            'client_id' => null,
            'status' => SaleStatus::Completed,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'discount_total' => $discount,
            'total' => $total,
            'payment_method' => fake()->randomElement(['cash', 'card', 'transfer']),
            'payment_status' => 'paid',
            'notes' => null,
            'sold_at' => now(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
