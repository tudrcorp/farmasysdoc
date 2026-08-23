<?php

namespace Database\Factories;

use App\Models\ShopAddress;
use App\Models\ShopCustomer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopAddress>
 */
class ShopAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pwa_customer_id' => ShopCustomer::factory(),
            'label' => fake()->optional()->randomElement(['Casa', 'Trabajo', 'Familia']),
            'address_line' => fake()->streetAddress(),
            'city' => 'Barinas',
            'state' => 'Barinas',
            'reference' => fake()->optional()->secondaryAddress(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (): array => [
            'is_primary' => true,
        ]);
    }
}
