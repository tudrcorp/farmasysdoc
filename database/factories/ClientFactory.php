<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'document_type' => 'CC',
            'document_number' => fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->randomElement(['Cundinamarca', 'Antioquia']),
            'country' => 'Colombia',
            'status' => 'active',
            'customer_discount' => 0,
            'created_by' => 'factory',
            'updated_by' => 'factory',
        ];
    }
}
