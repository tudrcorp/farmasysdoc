<?php

use App\Enums\EmployeeRequestStatus;
use App\Enums\TrainingAttendance;
use App\Enums\TrainingCourseType;
use App\Enums\TrainingSessionStatus;
use App\Jobs\NotifyTrainingEnrollmentJob;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\EmploymentCertificateSetting;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Training\TrainingCertificatePdfFactory;
use App\Services\Training\TrainingComplianceService;
use App\Services\Training\TrainingEnrollmentService;
use App\Services\Training\TrainingProgressService;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    $this->user = User::factory()->create();
    $this->enrollment = app(TrainingEnrollmentService::class);
    $this->progress = app(TrainingProgressService::class);
});

it('enrolls only active employees and notifies by both channels', function (): void {
    $session = TrainingSession::factory()->create();
    $active = Employee::factory()->create();
    $inactive = Employee::factory()->create(['is_active' => false]);

    $result = $this->enrollment->enroll($session, [$active->id, $inactive->id]);

    expect($result['enrolled'])->toBe(1)
        ->and($session->enrollments()->count())->toBe(1);

    Queue::assertPushed(NotifyTrainingEnrollmentJob::class, 2);
});

it('warns about capacity and approved absences', function (): void {
    $session = TrainingSession::factory()->create(['capacity' => 1]);
    $employees = Employee::factory()->count(2)->create();

    EmployeeRequest::factory()->create([
        'employee_id' => $employees[0]->id,
        'status' => EmployeeRequestStatus::Approved,
        'starts_on' => $session->starts_at->toDateString(),
        'ends_on' => $session->starts_at->toDateString(),
    ]);

    $warnings = $this->enrollment->warnings($session, $employees->pluck('id')->all());

    expect($warnings)->toHaveCount(2);
});

it('does not record attendance before the session starts', function (): void {
    $enrollment = TrainingEnrollment::factory()->create();

    $this->progress->markAttendance($enrollment, TrainingAttendance::Attended, $this->user);
})->throws(RuntimeException::class);

it('moves the session to in progress with the first attendance', function (): void {
    $session = TrainingSession::factory()->started()->create();
    $enrollment = TrainingEnrollment::factory()->create(['training_session_id' => $session->id]);

    $this->progress->markAttendance($enrollment, TrainingAttendance::Attended, $this->user);

    expect($session->refresh()->status)->toBe(TrainingSessionStatus::InProgress);
});

it('does not finish with pending attendance or missing results', function (): void {
    $course = TrainingCourse::factory()->create(['requires_evaluation' => true]);
    $session = TrainingSession::factory()->started()->create(['training_course_id' => $course->id]);
    $enrollment = TrainingEnrollment::factory()->create(['training_session_id' => $session->id]);

    expect(fn () => $this->progress->finish($session))->toThrow(RuntimeException::class, 'asistencia');

    $this->progress->markAttendance($enrollment, TrainingAttendance::Attended, $this->user);

    expect(fn () => $this->progress->finish($session->refresh()))->toThrow(RuntimeException::class, 'resultado');
});

it('issues certificates only to those who attended and passed', function (): void {
    $course = TrainingCourse::factory()->create(['requires_evaluation' => true, 'validity_months' => 12]);
    $session = TrainingSession::factory()->started()->create(['training_course_id' => $course->id]);
    $passed = TrainingEnrollment::factory()->create(['training_session_id' => $session->id]);
    $failed = TrainingEnrollment::factory()->create(['training_session_id' => $session->id]);
    $absent = TrainingEnrollment::factory()->create(['training_session_id' => $session->id]);

    $this->progress->markAttendance($passed, TrainingAttendance::Attended, $this->user);
    $this->progress->markAttendance($failed->refresh(), TrainingAttendance::Attended, $this->user);
    $this->progress->markAttendance($absent->refresh(), TrainingAttendance::Absent, $this->user);
    $this->progress->recordResult($passed->refresh(), true, 18, $this->user);
    $this->progress->recordResult($failed->refresh(), false, null, $this->user);
    $this->progress->finish($session->refresh());

    expect($passed->refresh()->canDownloadCertificate())->toBeTrue()
        ->and($passed->expires_on)->not->toBeNull()
        ->and($failed->refresh()->canDownloadCertificate())->toBeFalse()
        ->and($absent->refresh()->canDownloadCertificate())->toBeFalse();
});

it('builds the certificate with the signer of the employment certificate template', function (): void {
    EmploymentCertificateSetting::current()->update(['signer_name' => 'María Díaz']);

    $session = TrainingSession::factory()->started()->create();
    $enrollment = TrainingEnrollment::factory()->create(['training_session_id' => $session->id]);

    $this->progress->markAttendance($enrollment, TrainingAttendance::Attended, $this->user);
    $this->progress->finish($session->refresh());

    $data = app(TrainingCertificatePdfFactory::class)->viewData($enrollment->refresh());

    expect($data['setting']->signer_name)->toBe('María Díaz')
        ->and($data['reference'])->toStartWith('CAP-');
});

it('reports mandatory courses by job title and expiry', function (): void {
    $employee = Employee::factory()->create(['job_title' => 'Cajero']);
    $forCashiers = TrainingCourse::factory()->create(['is_mandatory' => true, 'required_job_titles' => ['cajero'], 'validity_months' => 1]);
    TrainingCourse::factory()->create(['is_mandatory' => true, 'required_job_titles' => ['Farmacéutico']]);

    $pending = app(TrainingComplianceService::class)->pendingFor($employee);

    expect($pending)->toHaveCount(1)
        ->and($pending->first()['status'])->toBe('Pendiente');

    $session = TrainingSession::factory()->create([
        'training_course_id' => $forCashiers->id,
        'status' => TrainingSessionStatus::Finished,
    ]);
    TrainingEnrollment::factory()->create([
        'training_session_id' => $session->id,
        'employee_id' => $employee->id,
        'attendance' => TrainingAttendance::Attended,
        'expires_on' => now()->subDay(),
    ]);

    expect(app(TrainingComplianceService::class)->pendingFor($employee)->first()['status'])->toBe('Vencido');
});

it('enrolls new hires in the next induction', function (): void {
    $employee = Employee::factory()->create(['job_title' => 'Cajero']);
    $course = TrainingCourse::factory()->create(['type' => TrainingCourseType::Induction]);
    $session = TrainingSession::factory()->create(['training_course_id' => $course->id]);

    $this->enrollment->enrollInInduction($employee);

    expect($session->enrollments()->where('employee_id', $employee->id)->exists())->toBeTrue();
});
