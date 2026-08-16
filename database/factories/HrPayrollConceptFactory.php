<?php

namespace Database\Factories;

use App\Enums\HrPayrollConceptApplication;
use App\Enums\HrPayrollConceptBehavior;
use App\Enums\HrPayrollConceptCurrency;
use App\Enums\HrPayrollConceptType;
use App\Models\HrPayrollConcept;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HrPayrollConcept>
 */
class HrPayrollConceptFactory extends Factory
{
    protected $model = HrPayrollConcept::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $application = fake()->randomElement(HrPayrollConceptApplication::cases());

        return [
            'name' => fake()->unique()->words(3, true),
            'type' => fake()->randomElement(HrPayrollConceptType::cases()),
            'application' => $application,
            'behavior' => HrPayrollConceptBehavior::Fixed,
            'amount' => fake()->randomFloat(2, 10, 500),
            'currency' => $application === HrPayrollConceptApplication::Legal
                ? HrPayrollConceptCurrency::Ves
                : fake()->randomElement(HrPayrollConceptCurrency::cases()),
            'is_active' => true,
        ];
    }

    public function legal(): static
    {
        return $this->state(fn (): array => [
            'application' => HrPayrollConceptApplication::Legal,
            'currency' => HrPayrollConceptCurrency::Ves,
        ]);
    }

    public function business(): static
    {
        return $this->state(fn (): array => [
            'application' => HrPayrollConceptApplication::Business,
        ]);
    }

    public function fixed(): static
    {
        return $this->state(fn (): array => [
            'behavior' => HrPayrollConceptBehavior::Fixed,
        ]);
    }

    public function percentage(): static
    {
        return $this->state(fn (): array => [
            'behavior' => HrPayrollConceptBehavior::Percentage,
            'amount' => fake()->randomFloat(2, 1, 30),
        ]);
    }
}
