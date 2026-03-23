<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('SUC-###'),
            'name' => fake()->streetName().' — '.fake()->city(),
            'legal_name' => fake()->optional()->company(),
            'tax_id' => fake()->optional()->numerify('9########'),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile_phone' => fake()->optional()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->randomElement(['Cundinamarca', 'Antioquia', 'Valle del Cauca']),
            'country' => 'Colombia',
            'is_headquarters' => false,
            'is_active' => true,
            'notes' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function headquarters(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_headquarters' => true,
        ]);
    }
}
