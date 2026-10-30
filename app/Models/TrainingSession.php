<?php

namespace App\Models;

use App\Enums\TrainingModality;
use App\Enums\TrainingSessionStatus;
use Database\Factories\TrainingSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fecha en que se dicta un curso del catálogo. Sin sucursal, es general para toda la empresa.
 */
class TrainingSession extends Model
{
    /** @use HasFactory<TrainingSessionFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'scheduled',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'training_course_id',
        'branch_id',
        'starts_at',
        'ends_at',
        'modality',
        'location',
        'meeting_url',
        'facilitator_employee_id',
        'facilitator_name',
        'capacity',
        'cost_usd',
        'cost_ves',
        'notes',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'modality' => TrainingModality::class,
            'capacity' => 'integer',
            'cost_usd' => 'decimal:2',
            'cost_ves' => 'decimal:2',
            'status' => TrainingSessionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<TrainingCourse, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function facilitatorEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'facilitator_employee_id');
    }

    /**
     * @return HasMany<TrainingEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class);
    }

    public function facilitatorLabel(): ?string
    {
        return $this->facilitatorEmployee?->fullName() ?? $this->facilitator_name;
    }

    public function scheduleLabel(): string
    {
        if ($this->starts_at->isSameDay($this->ends_at)) {
            return $this->starts_at->format('d/m/Y h:i a').' a '.$this->ends_at->format('h:i a');
        }

        return $this->starts_at->format('d/m/Y h:i a').' al '.$this->ends_at->format('d/m/Y h:i a');
    }

    public function optionLabel(): string
    {
        return "{$this->course?->name} · {$this->starts_at->format('d/m/Y')}";
    }
}
