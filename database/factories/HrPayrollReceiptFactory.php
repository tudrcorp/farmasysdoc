<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\HrPayrollReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HrPayrollReceipt>
 */
class HrPayrollReceiptFactory extends Factory
{
    protected $model = HrPayrollReceipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $monthly = fake()->randomFloat(2, 80, 200);
        $assignments = fake()->randomFloat(2, 0, 40);
        $deductions = fake()->randomFloat(2, 0, 15);
        $month = (int) now()->month;

        return [
            'employee_id' => Employee::factory(),
            'worker_name' => fake()->name(),
            'national_id' => 'V-'.fake()->numerify('##.###.###'),
            'month' => $month,
            'month_label' => 'AGOSTO',
            'year' => (int) now()->year,
            'branch_name' => 'Farmadoc Las Delicias',
            'branch_address' => fake()->streetAddress(),
            'legal_salary_monthly_ves' => $monthly,
            'legal_salary_biweekly_ves' => round($monthly / 2, 2),
            'assignments_ves' => $assignments,
            'deductions_ves' => $deductions,
            'total_ves' => round($monthly + $assignments - $deductions, 2),
            'items' => [
                [
                    'type' => 'assignment',
                    'name' => '23 Nro. DIAS TRABAJADOS',
                    'amount_ves' => $assignments,
                ],
                [
                    'type' => 'deduction',
                    'name' => 'SEGURO SOCIAL OBLIGATORIO (SSO)',
                    'amount_ves' => $deductions,
                ],
            ],
        ];
    }
}
