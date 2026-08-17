<?php

namespace Database\Factories;

use App\Enums\VenezuelanPagoMovilBank;
use App\Models\Branch;
use App\Models\PosTerminal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosTerminal>
 */
class PosTerminalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'code' => fake()->unique()->numerify('########'),
            'bank_code' => fake()->randomElement(array_column(VenezuelanPagoMovilBank::cases(), 'value')),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
