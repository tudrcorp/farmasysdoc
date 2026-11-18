<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanSubplan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlanSubplan>
 */
class PlanSubplanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory()->affiliateBands()->withSubplans(),
            'uuid' => (string) Str::uuid(),
            'name' => 'Plan '.fake()->unique()->word(),
            'description' => fake()->optional()->sentence(),
            'sort' => 0,
        ];
    }
}
