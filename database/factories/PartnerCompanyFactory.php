<?php

namespace Database\Factories;

use App\Enums\PartnerCompanyClientType;
use App\Enums\PartnerCompanyPaymentModality;
use App\Models\PartnerCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerCompany>
 */
class PartnerCompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legal_name' => fake()->company().' C.A.',
            'trade_name' => fake()->company(),
            'tax_id' => 'J'.fake()->unique()->numerify('#########'),
            'client_type' => PartnerCompanyClientType::Corporate,
            'payment_modality' => PartnerCompanyPaymentModality::Cash,
            'credit_limit_usd' => null,
            'assigned_credit_limit' => null,
            'discount_percentage' => fake()->randomFloat(2, 0, 15),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('0212#######'),
            'country' => 'VENEZUELA',
            'is_active' => true,
            'date_created' => now()->toDateString(),
        ];
    }

    public function onCredit(float $limit = 1000.0): static
    {
        return $this->state(fn (): array => [
            'payment_modality' => PartnerCompanyPaymentModality::Credit,
            'credit_limit_usd' => $limit,
            'assigned_credit_limit' => $limit,
        ]);
    }

    public function integral(float $limit = 1000.0): static
    {
        return $this->state(fn (): array => [
            'payment_modality' => PartnerCompanyPaymentModality::Integral,
            'credit_limit_usd' => $limit,
            'assigned_credit_limit' => $limit,
        ]);
    }
}
