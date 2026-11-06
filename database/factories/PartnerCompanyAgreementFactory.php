<?php

namespace Database\Factories;

use App\Enums\PartnerAgreementStatus;
use App\Enums\PartnerAgreementType;
use App\Enums\PartnerCompanyPaymentModality;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerCompanyAgreement>
 */
class PartnerCompanyAgreementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_company_id' => PartnerCompany::factory(),
            'type' => PartnerAgreementType::Initial,
            'starts_on' => now()->startOfDay()->toDateString(),
            'ends_on' => now()->startOfDay()->addYear()->subDay()->toDateString(),
            'auto_renews' => false,
            'payment_modality' => PartnerCompanyPaymentModality::Cash,
            'credit_limit_usd' => null,
            'discount_percentage' => 5,
            'terms' => fake()->paragraph(),
            'signed_document_path' => 'partner-companies/agreements/'.fake()->uuid().'.pdf',
            'signed_document_name' => 'convenio.pdf',
            'signed_on' => now()->toDateString(),
        ];
    }

    public function status(PartnerAgreementStatus $status): static
    {
        return $this->afterMaking(function (PartnerCompanyAgreement $agreement) use ($status): void {
            $agreement->forceFill(['status' => $status]);
        });
    }

    public function active(): static
    {
        return $this->status(PartnerAgreementStatus::Active)->afterMaking(function (PartnerCompanyAgreement $agreement): void {
            $agreement->forceFill(['activated_at' => now()]);
        });
    }

    public function onCredit(float $limit = 1000.0): static
    {
        return $this->state(fn (): array => [
            'payment_modality' => PartnerCompanyPaymentModality::Credit,
            'credit_limit_usd' => $limit,
        ]);
    }
}
