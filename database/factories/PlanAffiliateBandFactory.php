<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanAffiliateBand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanAffiliateBand>
 */
class PlanAffiliateBandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $min = fake()->unique()->numberBetween(1, 200) * 500;

        return [
            'plan_id' => Plan::factory()->affiliateBands(),
            'min_affiliates' => $min,
            'max_affiliates' => $min + 499,
            'sort' => 0,
        ];
    }

    public function openEnded(): static
    {
        return $this->state(fn (): array => ['max_affiliates' => null]);
    }
}
