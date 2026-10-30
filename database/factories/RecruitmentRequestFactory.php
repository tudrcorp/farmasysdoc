<?php

namespace Database\Factories;

use App\Enums\RecruitmentRequestPriority;
use App\Enums\RecruitmentRequestReason;
use App\Enums\RecruitmentRequestStatus;
use App\Models\Branch;
use App\Models\RecruitmentRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecruitmentRequest>
 */
class RecruitmentRequestFactory extends Factory
{
    protected $model = RecruitmentRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'job_title' => fake()->randomElement(['Cajero', 'Farmacéutico', 'Auxiliar de farmacia']),
            'vacancies' => 1,
            'reason' => RecruitmentRequestReason::Replacement,
            'priority' => RecruitmentRequestPriority::Normal,
            'status' => RecruitmentRequestStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => RecruitmentRequestStatus::Approved]);
    }
}
