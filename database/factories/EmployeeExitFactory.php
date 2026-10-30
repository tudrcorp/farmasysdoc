<?php

namespace Database\Factories;

use App\Enums\EmployeeExitReason;
use App\Models\Employee;
use App\Models\EmployeeExit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeExit>
 */
class EmployeeExitFactory extends Factory
{
    protected $model = EmployeeExit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'reason' => EmployeeExitReason::Resignation,
            'last_work_day' => now()->toDateString(),
        ];
    }
}
