<?php

namespace Database\Factories;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'inventory_id' => null,
            'movement_type' => InventoryMovementType::Purchase,
            'quantity' => fake()->randomFloat(3, 1, 100),
            'unit_cost' => fake()->optional()->randomFloat(4, 0.5, 200),
            'batch_number' => fake()->optional()->bothify('LOTE-####'),
            'expiry_date' => fake()->optional(0.6)->date(),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function outbound(): static
    {
        return $this->state(fn (array $attributes): array => [
            'movement_type' => InventoryMovementType::Sale,
            'quantity' => -1 * abs(fake()->randomFloat(3, 1, 20)),
        ]);
    }
}
