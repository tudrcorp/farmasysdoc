<?php

namespace App\Models;

use App\Enums\RecruitmentRequestPriority;
use App\Enums\RecruitmentRequestReason;
use App\Enums\RecruitmentRequestStatus;
use App\Enums\RecruitmentStage;
use Database\Factories\RecruitmentRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Solicitud de personal: una sucursal pide cubrir un cargo y el administrador la aprueba.
 */
class RecruitmentRequest extends Model
{
    /** @use HasFactory<RecruitmentRequestFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'job_title',
        'vacancies',
        'reason',
        'priority',
        'requirements',
        'reference_salary_usd',
        'needed_by',
        'status',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vacancies' => 'integer',
            'reason' => RecruitmentRequestReason::class,
            'priority' => RecruitmentRequestPriority::class,
            'status' => RecruitmentRequestStatus::class,
            'reference_salary_usd' => 'decimal:2',
            'needed_by' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
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

    public function isPending(): bool
    {
        return $this->status === RecruitmentRequestStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === RecruitmentRequestStatus::Approved;
    }

    public function hiredCount(): int
    {
        return $this->applications()->where('stage', RecruitmentStage::Hired)->count();
    }

    public function optionLabel(): string
    {
        $branch = $this->branch?->name ?? 'Sin sucursal';

        return "{$this->job_title} · {$branch}";
    }
}
