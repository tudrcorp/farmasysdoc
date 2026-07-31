<?php

namespace Database\Factories;

use App\Enums\InventoryAuditStatus;
use App\Models\Branch;
use App\Models\InventoryAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryAudit>
 */
class InventoryAuditFactory extends Factory
{
    protected $model = InventoryAudit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'status' => InventoryAuditStatus::Open,
            'started_by' => User::factory(),
            'closed_by' => null,
            'started_at' => now(),
            'closed_at' => null,
            'notes' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InventoryAuditStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $attributes['started_by'] ?? User::factory(),
        ]);
    }
}
