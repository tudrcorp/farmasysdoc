<?php

namespace Database\Factories;

use App\Models\ClientDiscountGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientDiscountGroup>
 */
class ClientDiscountGroupFactory extends Factory
{
    protected $model = ClientDiscountGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'discount_percent' => fake()->randomFloat(2, 1, 25),
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
