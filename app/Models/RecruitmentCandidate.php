<?php

namespace App\Models;

use App\Enums\RecruitmentCandidateSource;
use App\Enums\RecruitmentCvExtractionStatus;
use Database\Factories\RecruitmentCandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Persona en el banco de candidatos. El CV vive en el disco privado `local`
 * y los datos leídos por IA quedan sin revisar hasta que el analista los confirma.
 */
class RecruitmentCandidate extends Model
{
    /** @use HasFactory<RecruitmentCandidateFactory> */
    use HasFactory;

    public const string CV_DISK = 'local';

    public const string PHOTO_DISK = 'public';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'national_id',
        'phone',
        'email',
        'address',
        'birth_date',
        'desired_position',
        'years_of_experience',
        'education',
        'work_experience',
        'preferred_branch_id',
        'source',
        'cv_path',
        'cv_original_name',
        'photo_path',
        'cv_extraction_status',
        'cv_extraction_error',
        'cv_extraction_flags',
        'cv_photo_error',
        'reviewed_at',
        'reviewed_by',
        'employee_id',
    ];

    protected static function booted(): void
    {
        static::deleted(function (RecruitmentCandidate $candidate): void {
            if (filled($candidate->cv_path)) {
                Storage::disk(self::CV_DISK)->delete((string) $candidate->cv_path);
            }

            if (filled($candidate->photo_path)) {
                Storage::disk(self::PHOTO_DISK)->delete((string) $candidate->photo_path);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'years_of_experience' => 'integer',
            'source' => RecruitmentCandidateSource::class,
            'cv_extraction_status' => RecruitmentCvExtractionStatus::class,
            'cv_extraction_flags' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function preferredBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'preferred_branch_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<RecruitmentApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(RecruitmentApplication::class);
    }

    public function fullName(): string
    {
        $name = trim("{$this->first_name} {$this->last_name}");

        return $name !== '' ? $name : 'Candidato sin nombre';
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }

    public function isFieldFlagged(string $field): bool
    {
        return in_array($field, $this->cv_extraction_flags ?? [], true);
    }

    public function hasCv(): bool
    {
        return filled($this->cv_path) && Storage::disk(self::CV_DISK)->exists((string) $this->cv_path);
    }

    public function cvExtension(): string
    {
        return mb_strtolower(pathinfo((string) $this->cv_path, PATHINFO_EXTENSION));
    }

    public function photoUrl(): ?string
    {
        return filled($this->photo_path) ? Storage::disk(self::PHOTO_DISK)->url((string) $this->photo_path) : null;
    }
}
