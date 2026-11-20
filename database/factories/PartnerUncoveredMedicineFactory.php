<?php

namespace Database\Factories;

use App\Enums\UncoveredMedicineInventoryStatus;
use App\Enums\UncoveredMedicineManagementStatus;
use App\Models\PartnerCompany;
use App\Models\PartnerUncoveredMedicine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerUncoveredMedicine>
 */
class PartnerUncoveredMedicineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_company_id' => PartnerCompany::factory(),
            'external_id' => fake()->unique()->numberBetween(1, 9_000_000),
            'case_number' => fake()->numerify('#####').'-'.fake()->numerify('#####'),
            'coordination_id' => fake()->numberBetween(1, 9_000),
            'coordination_reference' => 'REF-'.fake()->numerify('#####'),
            'coordination_status' => UncoveredMedicineManagementStatus::Pending->value,
            'patient_name' => fake()->name(),
            'patient_national_id' => 'V-'.fake()->numerify('########'),
            'patient_phone' => fake()->numerify('0414#######'),
            'patient_state' => 'TRUJILLO',
            'patient_city' => 'TRUJILLO',
            'medicine_name' => strtoupper(fake()->words(3, true)),
            'indications' => fake()->sentence(),
            'quantity_label' => (string) fake()->numberBetween(1, 30),
            'duration_label' => fake()->numberBetween(1, 30).' días',
            'priority' => 'Estándar',
            'management_status' => UncoveredMedicineManagementStatus::Pending->value,
            'covered' => false,
            'inventory_status' => UncoveredMedicineInventoryStatus::Available->value,
            'registered_at' => now(),
            'remote_updated_at' => now(),
            'synced_at' => now(),
        ];
    }
}
