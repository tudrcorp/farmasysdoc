<?php

namespace Database\Factories;

use App\Enums\RecruitmentCvExtractionStatus;
use App\Models\RecruitmentCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecruitmentCandidate>
 */
class RecruitmentCandidateFactory extends Factory
{
    protected $model = RecruitmentCandidate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'national_id' => fake()->unique()->numerify('V-########'),
            'phone' => fake()->numerify('0414#######'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->streetAddress(),
            'cv_extraction_status' => RecruitmentCvExtractionStatus::Skipped,
            'reviewed_at' => now(),
        ];
    }

    public function unreviewed(): static
    {
        return $this->state(fn (): array => ['reviewed_at' => null]);
    }
}
