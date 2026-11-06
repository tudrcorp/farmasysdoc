<?php

namespace Database\Factories;

use App\Enums\PartnerCompanyDocumentType;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerCompanyDocument>
 */
class PartnerCompanyDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_company_id' => PartnerCompany::factory(),
            'type' => PartnerCompanyDocumentType::Rif,
            'file_path' => PartnerCompanyDocument::DIRECTORY.'/'.fake()->uuid().'.pdf',
            'original_name' => 'rif.pdf',
            'expires_at' => null,
            'sort' => 0,
        ];
    }
}
