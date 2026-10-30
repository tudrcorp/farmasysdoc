<?php

namespace App\Services\Training;

use App\Enums\EmployeeRequestStatus;
use App\Enums\TrainingAttendance;
use App\Enums\TrainingCourseType;
use App\Enums\TrainingSessionStatus;
use App\Jobs\NotifyTrainingEnrollmentJob;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Inscripción de empleados. Cupos y ausencias aprobadas avisan, pero no bloquean:
 * la decisión final es del administrador.
 */
class TrainingEnrollmentService
{
    /**
     * @param  list<int>  $employeeIds
     * @return array{enrolled: int, already_enrolled: int}
     */
    public function enroll(TrainingSession $session, array $employeeIds): array
    {
        if ($session->status !== TrainingSessionStatus::Scheduled) {
            throw new RuntimeException('Solo se puede inscribir en capacitaciones programadas.');
        }

        return DB::transaction(function () use ($session, $employeeIds): array {
            $existing = $session->enrollments()->pluck('employee_id')->all();
            $newIds = Employee::query()
                ->where('is_active', true)
                ->whereKey($employeeIds)
                ->whereKeyNot($existing)
                ->pluck('id');

            foreach ($newIds as $employeeId) {
                $enrollment = $session->enrollments()->create([
                    'employee_id' => $employeeId,
                    'attendance' => TrainingAttendance::Pending,
                ]);

                $this->notify($enrollment->id, NotifyTrainingEnrollmentJob::EVENT_ENROLLED);
            }

            return [
                'enrolled' => $newIds->count(),
                'already_enrolled' => count(array_intersect($employeeIds, $existing)),
            ];
        });
    }

    public function withdraw(TrainingEnrollment $enrollment): void
    {
        if ($enrollment->session->status !== TrainingSessionStatus::Scheduled) {
            throw new RuntimeException('Solo se puede retirar a alguien antes de que empiece la capacitación.');
        }

        $enrollment->delete();
    }

    /**
     * Inscribe al empleado nuevo en la próxima fecha de cada inducción que le aplique.
     * Si aún no hay fecha, queda como pendiente en su ficha.
     */
    public function enrollInInduction(Employee $employee): void
    {
        TrainingCourse::query()
            ->where('type', TrainingCourseType::Induction)
            ->where('is_active', true)
            ->get()
            ->filter(fn (TrainingCourse $course): bool => $course->appliesToJobTitle($employee->job_title))
            ->each(function (TrainingCourse $course) use ($employee): void {
                $session = $course->sessions()
                    ->where('status', TrainingSessionStatus::Scheduled)
                    ->where('starts_at', '>', now())
                    ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $employee->branch_id))
                    ->orderBy('starts_at')
                    ->first();

                if ($session !== null) {
                    $this->enroll($session, [$employee->id]);
                }
            });
    }

    /**
     * @param  list<int>  $employeeIds
     * @return list<string>
     */
    public function warnings(TrainingSession $session, array $employeeIds): array
    {
        $warnings = [];
        $currentCount = $session->enrollments()->count();
        $incoming = count(array_diff($employeeIds, $session->enrollments()->pluck('employee_id')->all()));

        if ($session->capacity !== null && $currentCount + $incoming > $session->capacity) {
            $warnings[] = "Se superan los cupos: {$session->capacity} disponibles y serían ".($currentCount + $incoming).' inscritos.';
        }

        $absent = $this->employeesAbsentDuring($session, $employeeIds);
        if ($absent->isNotEmpty()) {
            $warnings[] = 'Con ausencia aprobada en esa fecha: '.$absent->implode(', ').'.';
        }

        return $warnings;
    }

    /**
     * @param  list<int>  $employeeIds
     * @return Collection<int, string>
     */
    private function employeesAbsentDuring(TrainingSession $session, array $employeeIds): Collection
    {
        return EmployeeRequest::query()
            ->with('employee')
            ->whereIn('employee_id', $employeeIds)
            ->where('status', EmployeeRequestStatus::Approved)
            ->whereNotNull('starts_on')
            ->whereDate('starts_on', '<=', $session->ends_at->toDateString())
            ->whereDate('ends_on', '>=', $session->starts_at->toDateString())
            ->get()
            ->map(fn (EmployeeRequest $request): string => (string) $request->employee?->fullName())
            ->unique()
            ->values();
    }

    private function notify(int $enrollmentId, string $event): void
    {
        NotifyTrainingEnrollmentJob::dispatch($enrollmentId, $event, NotifyTrainingEnrollmentJob::CHANNEL_MAIL);
        NotifyTrainingEnrollmentJob::dispatch($enrollmentId, $event, NotifyTrainingEnrollmentJob::CHANNEL_WHATSAPP);
    }
}
