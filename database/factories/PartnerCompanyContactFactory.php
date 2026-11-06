<?php

namespace Database\Factories;

use App\Models\PartnerCompany;
use App\Models\PartnerCompanyContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerCompanyContact>
 */
class PartnerCompanyContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_company_id' => PartnerCompany::factory(),
            'name' => fake()->name(),
            'position' => fake()->jobTitle(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('0414#######'),
            'is_responsible' => false,
            'sort' => 0,
        ];
    }

    public function responsible(): static
    {
        return $this->state(fn (): array => ['is_responsible' => true]);
    }
}
