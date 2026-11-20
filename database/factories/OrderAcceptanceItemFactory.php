<?php

namespace Database\Factories;

use App\Models\OrderAcceptance;
use App\Models\OrderAcceptanceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderAcceptanceItem>
 */
class OrderAcceptanceItemFactory extends Factory
{
    protected $model = OrderAcceptanceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unit = fake()->randomFloat(2, 1, 40);
        $line = round($unit * $quantity, 2);
        $rate = 40.0;

        return [
            'order_acceptance_id' => OrderAcceptance::factory(),
            'order_item_id' => null,
            'product_id' => null,
            'product_name' => fake()->words(3, true),
            'barcode' => fake()->numerify('759##########'),
            'sku' => fake()->bothify('SKU-####'),
            'quantity_ordered' => $quantity,
            'quantity_on_hand' => $quantity + 2,
            'accepted' => true,
            'rejection_note' => null,
            'unit_price_usd' => $unit,
            'line_total_usd' => $line,
            'line_total_ves' => round($line * $rate, 2),
        ];
    }
}
