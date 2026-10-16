<?php

namespace Database\Factories;

use App\Enums\SalesBookStatus;
use App\Models\Branch;
use App\Models\SalesBook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesBook>
 */
class SalesBookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $snapshot = (string) json_encode(['rows' => [], 'totals' => ['total' => 0.0, 'retained' => 0.0], 'z_reports_count' => 0]);

        return [
            'branch_id' => Branch::factory(),
            'year' => 2026,
            'month' => 9,
            'half' => 2,
            'period_start' => '2026-09-16',
            'period_end' => '2026-09-30',
            'version' => 1,
            'status' => SalesBookStatus::Closed,
            'snapshot' => $snapshot,
            'content_hash' => hash('sha256', $snapshot),
            'closed_by' => 'factory',
            'closed_at' => now(),
        ];
    }
}
