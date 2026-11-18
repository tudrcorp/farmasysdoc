<?php

namespace Database\Factories;

use App\Models\Formulary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Formulary>
 */
class FormularyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'CAT-'.fake()->unique()->numerify('####'),
            'name' => 'Catálogo '.fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
