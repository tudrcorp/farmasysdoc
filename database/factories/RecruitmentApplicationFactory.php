<?php

namespace Database\Factories;

use App\Enums\RecruitmentStage;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecruitmentApplication>
 */
class RecruitmentApplicationFactory extends Factory
{
    protected $model = RecruitmentApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recruitment_request_id' => RecruitmentRequest::factory()->approved(),
            'recruitment_candidate_id' => RecruitmentCandidate::factory(),
            'stage' => RecruitmentStage::Applied,
        ];
    }
}
