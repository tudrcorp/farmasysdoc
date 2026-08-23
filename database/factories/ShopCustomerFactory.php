<?php

namespace Database\Factories;

use App\Models\ShopCustomer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShopCustomer>
 */
class ShopCustomerFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'document_type' => 'V',
            'document_number' => fake()->unique()->numerify('########'),
            'phone' => null,
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'google_id' => null,
            'google_avatar' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function phoneAccount(): static
    {
        return $this->state(fn (): array => [
            'document_type' => null,
            'document_number' => null,
            'phone' => '58'.fake()->unique()->numerify('412#######'),
        ]);
    }

    public function googleAccount(): static
    {
        return $this->state(fn (): array => [
            'document_type' => null,
            'document_number' => null,
            'phone' => null,
            'password' => null,
            'google_id' => fake()->unique()->numerify('############'),
        ]);
    }
}
