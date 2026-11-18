<?php

namespace Database\Factories;

use App\Enums\PlanPremiumMode;
use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $premiumUsd = fake()->randomFloat(2, 5, 80);

        return [
            'code' => 'PLN-'.fake()->unique()->numerify('####'),
            'name' => 'Plan '.fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'status' => PlanStatus::Draft,
            'premium_mode' => PlanPremiumMode::Flat,
            'monthly_premium_usd' => $premiumUsd,
            'monthly_premium_ves' => round($premiumUsd * 400, 2),
            'valid_from' => now()->startOfMonth()->toDateString(),
            'valid_until' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => PlanStatus::Active]);
    }

    public function withAgeRanges(): static
    {
        return $this->state(fn (): array => [
            'premium_mode' => PlanPremiumMode::AgeRanges,
            'monthly_premium_usd' => null,
            'monthly_premium_ves' => null,
        ]);
    }

    public function consumptionFund(): static
    {
        return $this->state(fn (): array => [
            'premium_mode' => PlanPremiumMode::ConsumptionFund,
            'monthly_premium_usd' => null,
            'monthly_premium_ves' => null,
        ]);
    }

    public function affiliateBands(): static
    {
        return $this->state(fn (): array => [
            'premium_mode' => PlanPremiumMode::AffiliateBands,
            'monthly_premium_usd' => null,
            'monthly_premium_ves' => null,
        ]);
    }

    public function withSubplans(): static
    {
        return $this->state(fn (): array => ['has_subplans' => true]);
    }

    public function retail(): static
    {
        return $this->state(fn (): array => [
            'premium_mode' => PlanPremiumMode::Retail,
            'monthly_premium_usd' => null,
            'monthly_premium_ves' => null,
        ]);
    }
}
