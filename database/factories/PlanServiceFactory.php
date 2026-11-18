<?php

namespace Database\Factories;

use App\Models\PlanService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanService>
 */
class PlanServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'SRV-'.fake()->unique()->numerify('####'),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
