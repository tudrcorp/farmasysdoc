<?php

namespace Database\Factories;

use App\Models\ApiClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiClient>
 */
class ApiClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::random(60);

        return [
            'name' => 'Aliado '.fake()->company(),
            'token_hash' => ApiClient::hashToken($token),
            'is_active' => true,
            'last_used_at' => null,
            'allowed_ips' => null,
        ];
    }
}
