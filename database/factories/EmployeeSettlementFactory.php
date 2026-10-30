<?php

namespace Database\Factories;

use App\Models\EmployeeExit;
use App\Models\EmployeeSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSettlement>
 */
class EmployeeSettlementFactory extends Factory
{
    protected $model = EmployeeSettlement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_exit_id' => EmployeeExit::factory(),
            'hired_at' => now()->subYears(2)->toDateString(),
            'last_work_day' => now()->toDateString(),
            'service_label' => '2 años, 0 meses y 0 días',
            'monthly_salary_usd' => 300,
            'normal_daily_usd' => 10,
            'integral_daily_usd' => 11,
            'parameters' => [],
        ];
    }
}
