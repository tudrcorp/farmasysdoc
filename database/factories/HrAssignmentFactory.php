<?php

namespace Database\Factories;

use App\Enums\HrRecurrence;
use App\Models\Employee;
use App\Models\HrAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HrAssignment>
 */
class HrAssignmentFactory extends Factory
{
    protected $model = HrAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'concept' => fake()->sentence(3),
            'amount_usd' => fake()->randomFloat(2, 10, 100),
            'recurrence' => HrRecurrence::Once,
            'applies_on' => now()->startOfMonth()->day(15)->toDateString(),
            'starts_on' => null,
            'ends_on' => null,
            'is_active' => true,
        ];
    }
}
