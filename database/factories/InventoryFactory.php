<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'product_id' => Product::factory(),
            'sale_price' => fake()->randomFloat(2, 5, 500),
            'cost_price' => fake()->optional()->randomFloat(2, 3, 400),
            'tax_rate' => fake()->randomElement([0.0, 5.0, 19.0]),
            'discount_percent' => 0.0,
            'quantity' => fake()->randomFloat(3, 0, 500),
            'reserved_quantity' => 0,
            'reorder_point' => fake()->optional()->randomFloat(3, 5, 50),
            'minimum_stock' => fake()->optional()->randomFloat(3, 5, 30),
            'maximum_stock' => fake()->optional()->randomFloat(3, 200, 1000),
            'storage_location' => fake()->optional()->randomElement(['Pasillo A', 'Nevera 2', 'Góndola central']),
            'allow_negative_stock' => false,
            'last_movement_at' => null,
            'last_stock_take_at' => null,
            'notes' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
