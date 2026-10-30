<?php

namespace App\Models;

use App\Enums\TrainingCourseType;
use Database\Factories\TrainingCourseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Taller, capacitación o inducción del catálogo de formación.
 */
class TrainingCourse extends Model
{
    /** @use HasFactory<TrainingCourseFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'description',
        'duration_hours',
        'is_mandatory',
        'required_job_titles',
        'validity_months',
        'requires_evaluation',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TrainingCourseType::class,
            'duration_hours' => 'decimal:2',
            'is_mandatory' => 'boolean',
            'required_job_titles' => 'array',
            'validity_months' => 'integer',
            'requires_evaluation' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<TrainingSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    /**
     * Sin cargos indicados, el curso aplica a todo el personal.
     */
    public function appliesToJobTitle(?string $jobTitle): bool
    {
        $titles = array_map(fn (string $title): string => mb_strtolower(trim($title)), $this->required_job_titles ?? []);

        return $titles === [] || in_array(mb_strtolower(trim((string) $jobTitle)), $titles, true);
    }
}
