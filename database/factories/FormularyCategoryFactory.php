<?php

namespace Database\Factories;

use App\Models\FormularyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormularyCategory>
 */
class FormularyCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'CTG-'.fake()->unique()->numerify('####'),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
