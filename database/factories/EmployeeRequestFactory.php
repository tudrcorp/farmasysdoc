<?php

namespace Database\Factories;

use App\Enums\EmployeeRequestStatus;
use App\Enums\EmployeeRequestType;
use App\Enums\EmploymentCertificateSalaryBasis;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeRequest>
 */
class EmployeeRequestFactory extends Factory
{
    protected $model = EmployeeRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = now()->addDays(fake()->numberBetween(5, 40))->startOfDay();

        return [
            'employee_id' => Employee::factory(),
            'type' => EmployeeRequestType::Leave,
            'status' => EmployeeRequestStatus::Pending,
            'starts_on' => $startsOn,
            'ends_on' => $startsOn->copy()->addDays(1),
            'days_count' => 2,
            'reason' => fake()->sentence(),
        ];
    }

    public function certificate(EmploymentCertificateSalaryBasis $basis = EmploymentCertificateSalaryBasis::Legal): static
    {
        return $this->state(fn (): array => [
            'type' => EmployeeRequestType::EmploymentCertificate,
            'salary_basis' => $basis,
            'starts_on' => null,
            'ends_on' => null,
            'days_count' => null,
        ]);
    }

    public function vacation(): static
    {
        return $this->state(fn (): array => [
            'type' => EmployeeRequestType::Vacation,
            'entitled_days' => 15,
        ]);
    }

    public function medicalRest(): static
    {
        return $this->state(fn (): array => [
            'type' => EmployeeRequestType::MedicalRest,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => EmployeeRequestStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }
}
