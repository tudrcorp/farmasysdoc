<?php

namespace Database\Factories;

use App\Models\PlanAffiliateBand;
use App\Models\PlanAffiliateBandPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanAffiliateBandPrice>
 */
class PlanAffiliateBandPriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_affiliate_band_id' => PlanAffiliateBand::factory(),
            'plan_subplan_id' => null,
            'price_usd' => fake()->randomFloat(2, 3, 12),
        ];
    }
}
