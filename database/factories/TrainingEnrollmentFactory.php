<?php

namespace Database\Factories;

use App\Enums\TrainingAttendance;
use App\Models\Employee;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingEnrollment>
 */
class TrainingEnrollmentFactory extends Factory
{
    protected $model = TrainingEnrollment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_session_id' => TrainingSession::factory(),
            'employee_id' => Employee::factory(),
            'attendance' => TrainingAttendance::Pending,
        ];
    }
}
