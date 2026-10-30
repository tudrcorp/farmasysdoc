<?php

use App\Enums\RecruitmentRequestStatus;
use App\Enums\RecruitmentStage;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentRequest;
use App\Models\User;
use App\Services\Recruitment\RecruitmentHireService;
use App\Services\Recruitment\RecruitmentRequestReviewer;
use App\Services\Recruitment\RecruitmentSelectionService;
use Illuminate\Support\Facades\Storage;

function recruitmentHireData(RecruitmentRequest $request): array
{
    return [
        'branch_id' => $request->branch_id,
        'job_title' => 'Cajero',
        'hired_at' => now()->toDateString(),
        'monthly_salary_usd' => 300,
        'legal_salary_ves' => 5000,
    ];
}

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $this->user = User::factory()->create();
});

it('only lets administrators decide on requests', function (): void {
    $request = RecruitmentRequest::factory()->create();

    app(RecruitmentRequestReviewer::class)->approve($request, $this->user);
})->throws(RuntimeException::class);

it('rejects unreviewed candidates in the selection', function (): void {
    $candidate = RecruitmentCandidate::factory()->unreviewed()->create();
    $request = RecruitmentRequest::factory()->approved()->create();

    app(RecruitmentSelectionService::class)->apply($candidate, $request, $this->user);
})->throws(RuntimeException::class);

it('records every stage change', function (): void {
    $application = RecruitmentApplication::factory()->create();

    app(RecruitmentSelectionService::class)->move($application, RecruitmentStage::Interview, $this->user, 'Buen perfil');

    expect($application->refresh()->stage)->toBe(RecruitmentStage::Interview)
        ->and($application->events()->first())
        ->from_stage->toBe(RecruitmentStage::Applied)
        ->to_stage->toBe(RecruitmentStage::Interview)
        ->note->toBe('Buen perfil');
});

it('does not allow hiring by moving the stage', function (): void {
    $application = RecruitmentApplication::factory()->create();

    app(RecruitmentSelectionService::class)->move($application, RecruitmentStage::Hired, $this->user);
})->throws(RuntimeException::class);

it('only hires from the offer stage', function (): void {
    $application = RecruitmentApplication::factory()->create();

    app(RecruitmentHireService::class)->hire($application, recruitmentHireData($application->recruitmentRequest), $this->user);
})->throws(RuntimeException::class);

it('creates the employee with photo and cv and fills the request', function (): void {
    Storage::disk('public')->put('recruitment/photos/foto.jpg', 'foto');
    Storage::disk('local')->put('recruitment/cvs/cv.pdf', 'cv');

    $candidate = RecruitmentCandidate::factory()->create([
        'national_id' => 'V-12345678',
        'photo_path' => 'recruitment/photos/foto.jpg',
        'cv_path' => 'recruitment/cvs/cv.pdf',
    ]);
    $request = RecruitmentRequest::factory()->approved()->create(['branch_id' => Branch::factory()]);
    $application = RecruitmentApplication::factory()->create([
        'recruitment_request_id' => $request->id,
        'recruitment_candidate_id' => $candidate->id,
        'stage' => RecruitmentStage::Offer,
    ]);
    $rival = RecruitmentApplication::factory()->create(['recruitment_request_id' => $request->id]);

    $employee = app(RecruitmentHireService::class)->hire($application, recruitmentHireData($request), $this->user);

    expect($employee->national_id)->toBe('V-12345678')
        ->and($employee->first_name)->toBe($candidate->first_name)
        ->and($candidate->refresh()->employee_id)->toBe($employee->id)
        ->and($application->refresh()->stage)->toBe(RecruitmentStage::Hired)
        ->and($request->refresh()->status)->toBe(RecruitmentRequestStatus::Filled)
        ->and($rival->refresh()->stage)->toBe(RecruitmentStage::Discarded);

    Storage::disk('public')->assertExists($employee->photo_path);
    Storage::disk('local')->assertExists($employee->cv_path);
});

it('updates the existing employee on a rehire', function (): void {
    $existing = Employee::factory()->create(['national_id' => '12345678', 'is_active' => false]);
    $candidate = RecruitmentCandidate::factory()->create(['national_id' => 'V-12345678']);
    $application = RecruitmentApplication::factory()->create([
        'recruitment_candidate_id' => $candidate->id,
        'stage' => RecruitmentStage::Offer,
    ]);

    $employee = app(RecruitmentHireService::class)->hire($application, recruitmentHireData($application->recruitmentRequest), $this->user);

    expect($employee->id)->toBe($existing->id)
        ->and($employee->is_active)->toBeTrue()
        ->and(Employee::query()->count())->toBe(1);
});
