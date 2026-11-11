<?php

namespace Database\Factories;

use App\Enums\OtpCodePurpose;
use App\Enums\OtpCodeStatus;
use App\Models\Branch;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OtpCode>
 */
class OtpCodeFactory extends Factory
{
    protected $model = OtpCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purpose' => fake()->randomElement(OtpCodePurpose::cases()),
            'status' => OtpCodeStatus::Pending,
            'code' => fake()->numerify('######'),
            'fingerprint' => null,
            'requested_by_user_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'summary' => fake()->sentence(),
            'details' => [],
            'failed_attempts' => 0,
            'expires_at' => now()->addSeconds(OtpCode::TTL_SECONDS),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function used(?User $usedBy = null): static
    {
        return $this->state(fn (): array => [
            'status' => OtpCodeStatus::Used,
            'used_by_user_id' => $usedBy?->getKey() ?? User::factory(),
            'used_at' => now(),
            'used_in' => 'Pruebas',
        ]);
    }
}
