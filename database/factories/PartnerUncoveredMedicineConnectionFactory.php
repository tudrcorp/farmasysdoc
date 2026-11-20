<?php

namespace Database\Factories;

use App\Enums\UncoveredMedicineSyncStatus;
use App\Models\PartnerCompany;
use App\Models\PartnerUncoveredMedicineConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerUncoveredMedicineConnection>
 */
class PartnerUncoveredMedicineConnectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_company_id' => PartnerCompany::factory(),
            'token' => 'icp_example.'.fake()->sha1(),
            'sync_status' => UncoveredMedicineSyncStatus::Idle,
            'sync_run_id' => null,
            'sync_started_at' => null,
            'last_synced_at' => null,
            'last_error' => null,
            'rate_limit_limit' => 60,
            'rate_limit_remaining' => 60,
            'profile' => [
                'nombre' => fake()->company(),
                'limite_por_minuto' => 60,
            ],
        ];
    }
}
