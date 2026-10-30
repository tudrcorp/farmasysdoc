<?php

namespace App\Models;

use App\Enums\TrainingAttendance;
use App\Enums\TrainingSessionStatus;
use Database\Factories\TrainingEnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Participación de un empleado en una capacitación: asistencia, resultado y vigencia.
 */
class TrainingEnrollment extends Model
{
    /** @use HasFactory<TrainingEnrollmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'training_session_id',
        'employee_id',
        'attendance',
        'passed',
        'score',
        'expires_on',
        'recorded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance' => TrainingAttendance::class,
            'passed' => 'boolean',
            'score' => 'decimal:2',
            'expires_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<TrainingSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Completó el curso: asistió y, si el curso se evalúa, aprobó.
     */
    public function isCompleted(): bool
    {
        if ($this->attendance !== TrainingAttendance::Attended) {
            return false;
        }

        return ! $this->session->course->requires_evaluation || $this->passed === true;
    }

    public function canDownloadCertificate(): bool
    {
        return $this->session->status === TrainingSessionStatus::Finished && $this->isCompleted();
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast() && ! $this->expires_on->isToday();
    }
}
