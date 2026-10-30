<?php

namespace App\Services\Training;

use App\Enums\TrainingAttendance;
use App\Enums\TrainingSessionStatus;
use App\Jobs\NotifyTrainingEnrollmentJob;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Avance de una capacitación: asistencia, resultados y cierre.
 */
class TrainingProgressService
{
    public function markAttendance(TrainingEnrollment $enrollment, TrainingAttendance $attendance, User $user): void
    {
        $session = $enrollment->session;
        $this->ensureCanRecord($session);

        DB::transaction(function () use ($enrollment, $attendance, $session, $user): void {
            $enrollment->update([
                'attendance' => $attendance,
                'passed' => $attendance === TrainingAttendance::Attended ? $enrollment->passed : null,
                'score' => $attendance === TrainingAttendance::Attended ? $enrollment->score : null,
                'recorded_by' => $user->id,
            ]);

            if ($session->status === TrainingSessionStatus::Scheduled) {
                $session->update(['status' => TrainingSessionStatus::InProgress]);
            }
        });
    }

    public function recordResult(TrainingEnrollment $enrollment, bool $passed, ?float $score, User $user): void
    {
        $this->ensureCanRecord($enrollment->session);

        if ($enrollment->attendance !== TrainingAttendance::Attended) {
            throw new RuntimeException('Solo se evalúa a quien asistió.');
        }

        $enrollment->update(['passed' => $passed, 'score' => $score, 'recorded_by' => $user->id]);
    }

    public function start(TrainingSession $session): void
    {
        if ($session->status !== TrainingSessionStatus::Scheduled) {
            throw new RuntimeException('Esta capacitación ya no está programada.');
        }

        $session->update(['status' => TrainingSessionStatus::InProgress]);
    }

    /**
     * Exige asistencia y resultados completos, y fija hasta cuándo vale cada certificación.
     */
    public function finish(TrainingSession $session): void
    {
        if (! $session->status->isOpen()) {
            throw new RuntimeException('Esta capacitación ya está cerrada.');
        }

        $enrollments = $session->enrollments()->get();

        if ($enrollments->isEmpty()) {
            throw new RuntimeException('No hay participantes inscritos.');
        }

        if ($enrollments->contains(fn (TrainingEnrollment $enrollment): bool => $enrollment->attendance === TrainingAttendance::Pending)) {
            throw new RuntimeException('Falta marcar la asistencia de algunos participantes.');
        }

        $course = $session->course;
        $missingResult = $course->requires_evaluation && $enrollments->contains(
            fn (TrainingEnrollment $enrollment): bool => $enrollment->attendance === TrainingAttendance::Attended && $enrollment->passed === null,
        );

        if ($missingResult) {
            throw new RuntimeException('Falta registrar el resultado de algunos participantes.');
        }

        DB::transaction(function () use ($session, $enrollments, $course): void {
            $session->update(['status' => TrainingSessionStatus::Finished]);

            if ($course->validity_months === null) {
                return;
            }

            $expiresOn = $session->ends_at->copy()->addMonthsNoOverflow($course->validity_months)->toDateString();

            $enrollments
                ->each(fn (TrainingEnrollment $enrollment) => $enrollment->setRelation('session', $session))
                ->filter(fn (TrainingEnrollment $enrollment): bool => $enrollment->isCompleted())
                ->each(fn (TrainingEnrollment $enrollment) => $enrollment->update(['expires_on' => $expiresOn]));
        });
    }

    public function cancel(TrainingSession $session): void
    {
        if (! $session->status->isOpen()) {
            throw new RuntimeException('Esta capacitación ya está cerrada.');
        }

        DB::transaction(function () use ($session): void {
            $session->update(['status' => TrainingSessionStatus::Cancelled]);

            $session->enrollments()->pluck('id')->each(function (int $enrollmentId): void {
                NotifyTrainingEnrollmentJob::dispatch($enrollmentId, NotifyTrainingEnrollmentJob::EVENT_CANCELLED, NotifyTrainingEnrollmentJob::CHANNEL_MAIL);
                NotifyTrainingEnrollmentJob::dispatch($enrollmentId, NotifyTrainingEnrollmentJob::EVENT_CANCELLED, NotifyTrainingEnrollmentJob::CHANNEL_WHATSAPP);
            });
        });
    }

    private function ensureCanRecord(TrainingSession $session): void
    {
        if (! $session->status->isOpen()) {
            throw new RuntimeException('Esta capacitación ya está cerrada.');
        }

        if ($session->starts_at->isFuture()) {
            throw new RuntimeException('La capacitación todavía no ha empezado.');
        }
    }
}
