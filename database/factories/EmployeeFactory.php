<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'national_id' => fake()->unique()->numerify('V-########'),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->streetAddress(),
            'monthly_salary_usd' => fake()->randomFloat(2, 200, 800),
            'first_half_usd_cash' => 0,
            'second_half_usd_cash' => 0,
            'branch_id' => Branch::factory(),
            'is_active' => true,
        ];
    }
}
