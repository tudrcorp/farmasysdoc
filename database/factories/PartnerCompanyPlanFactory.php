<?php

namespace Database\Factories;

use App\Models\PartnerCompany;
use App\Models\PartnerCompanyPlan;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerCompanyPlan>
 */
class PartnerCompanyPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'partner_company_id' => PartnerCompany::factory(),
            'applies_intermediary_profit' => false,
            'applies_partner_profit' => false,
        ];
    }

    /**
     * Asignación de un plan de fondo de consumo con monto autorizado y ambos porcentajes.
     */
    public function withConsumptionFund(float $amountUsd = 1000.0): static
    {
        return $this->state(fn (): array => [
            'plan_id' => Plan::factory()->consumptionFund(),
            'authorized_amount_usd' => $amountUsd,
            'authorized_amount_ves' => round($amountUsd * 400, 2),
            'applies_intermediary_profit' => true,
            'intermediary_profit_percent' => 5,
            'applies_partner_profit' => true,
            'partner_profit_percent' => 3,
        ]);
    }
}
