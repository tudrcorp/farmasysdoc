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
        $taxTotal = 0.0;
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
            'payment_method' => 'efectivo_usd',
            'payment_usd' => $total,
            'payment_ves' => 0.0,
            'bcv_ves_per_usd' => null,
            'reference' => null,
            'payment_status' => 'paid',
            'notes' => null,
            'sold_at' => now(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
