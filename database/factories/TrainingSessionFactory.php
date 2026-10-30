<?php

namespace Database\Factories;

use App\Enums\TrainingModality;
use App\Enums\TrainingSessionStatus;
use App\Models\TrainingCourse;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingSession>
 */
class TrainingSessionFactory extends Factory
{
    protected $model = TrainingSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_course_id' => TrainingCourse::factory(),
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
            'modality' => TrainingModality::InPerson,
            'status' => TrainingSessionStatus::Scheduled,
        ];
    }

    public function started(): static
    {
        return $this->state(fn (): array => ['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(3)]);
    }
}
