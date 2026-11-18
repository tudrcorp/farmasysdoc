<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanAgeRange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanAgeRange>
 */
class PlanAgeRangeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minAge = fake()->numberBetween(0, 60);
        $premiumUsd = fake()->randomFloat(2, 5, 80);

        return [
            'plan_id' => Plan::factory()->withAgeRanges(),
            'min_age' => $minAge,
            'max_age' => $minAge + fake()->numberBetween(5, 20),
            'monthly_premium_usd' => $premiumUsd,
            'monthly_premium_ves' => round($premiumUsd * 400, 2),
            'sort' => 0,
        ];
    }
}
