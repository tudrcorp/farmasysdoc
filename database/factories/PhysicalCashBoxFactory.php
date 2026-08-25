<?php

namespace Database\Factories;

use App\Models\PhysicalCashBox;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhysicalCashBox>
 */
class PhysicalCashBoxFactory extends Factory
{
    protected $model = PhysicalCashBox::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount_usd' => fake()->randomFloat(2, 0, 500),
            'amount_ves' => fake()->randomFloat(2, 0, 50000),
            'is_open' => false,
            'opened_at' => null,
            'closed_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_open' => true,
            'opened_at' => now(),
            'closed_at' => null,
        ]);
    }
}
