<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\PhysicalCashBox;
use App\Models\PhysicalCashBoxCloseReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhysicalCashBoxCloseReport>
 */
class PhysicalCashBoxCloseReportFactory extends Factory
{
    protected $model = PhysicalCashBoxCloseReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $declaredUsd = fake()->randomFloat(2, 0, 200);
        $expectedUsd = $declaredUsd;
        $declaredVes = fake()->randomFloat(2, 0, 20000);
        $expectedVes = $declaredVes;

        return [
            'physical_cash_box_id' => PhysicalCashBox::factory(),
            'user_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'opened_at' => now()->subHours(8),
            'closed_at' => now(),
            'declared_usd' => $declaredUsd,
            'declared_ves' => $declaredVes,
            'expected_usd' => $expectedUsd,
            'expected_ves' => $expectedVes,
            'difference_usd' => 0,
            'difference_ves' => 0,
            'pos_declared_ves' => 0,
            'pos_system_ves' => 0,
            'pos_difference_ves' => 0,
            'has_cash_mismatch' => false,
            'has_pos_mismatch' => false,
            'has_mismatch' => false,
            'pos_lines' => [],
            'report_snapshot' => [],
        ];
    }
}
