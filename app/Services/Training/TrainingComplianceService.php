<?php

namespace App\Services\Training;

use App\Models\Employee;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use Illuminate\Support\Collection;

/**
 * Cursos obligatorios que le faltan a un empleado, según su cargo.
 */
class TrainingComplianceService
{
    /**
     * @return Collection<int, array{course: TrainingCourse, status: string}>
     */
    public function pendingFor(Employee $employee): Collection
    {
        $latestCompleted = $employee->trainingEnrollments()
            ->with('session.course')
            ->get()
            ->filter(fn (TrainingEnrollment $enrollment): bool => $enrollment->canDownloadCertificate())
            ->sortByDesc(fn (TrainingEnrollment $enrollment) => $enrollment->session->ends_at)
            ->unique(fn (TrainingEnrollment $enrollment): int => $enrollment->session->training_course_id)
            ->keyBy(fn (TrainingEnrollment $enrollment): int => $enrollment->session->training_course_id);

        return TrainingCourse::query()
            ->where('is_active', true)
            ->where('is_mandatory', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (TrainingCourse $course): bool => $course->appliesToJobTitle($employee->job_title))
            ->map(function (TrainingCourse $course) use ($latestCompleted): ?array {
                $enrollment = $latestCompleted->get($course->id);

                if ($enrollment === null) {
                    return ['course' => $course, 'status' => 'Pendiente'];
                }

                return $enrollment->isExpired() ? ['course' => $course, 'status' => 'Vencido'] : null;
            })
            ->filter()
            ->values();
    }
}
