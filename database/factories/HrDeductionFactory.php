<?php

namespace Database\Factories;

use App\Enums\HrPayCurrencyBucket;
use App\Enums\HrRecurrence;
use App\Models\Employee;
use App\Models\HrDeduction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HrDeduction>
 */
class HrDeductionFactory extends Factory
{
    protected $model = HrDeduction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'concept' => fake()->sentence(3),
            'amount_usd' => fake()->randomFloat(2, 5, 50),
            'pay_currency_bucket' => HrPayCurrencyBucket::Ves,
            'recurrence' => HrRecurrence::Once,
            'applies_on' => now()->startOfMonth()->day(15)->toDateString(),
            'starts_on' => null,
            'ends_on' => null,
            'is_active' => true,
        ];
    }
}
