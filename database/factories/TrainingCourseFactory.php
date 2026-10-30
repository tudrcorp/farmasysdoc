<?php

namespace Database\Factories;

use App\Enums\TrainingCourseType;
use App\Models\TrainingCourse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingCourse>
 */
class TrainingCourseFactory extends Factory
{
    protected $model = TrainingCourse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Atención al cliente', 'Manejo de la caja', 'Buenas prácticas de farmacia']),
            'type' => TrainingCourseType::Training,
            'duration_hours' => 4,
        ];
    }
}
