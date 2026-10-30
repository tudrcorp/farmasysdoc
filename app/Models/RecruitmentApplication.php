<?php

namespace App\Models;

use App\Enums\RecruitmentStage;
use Database\Factories\RecruitmentApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proceso de selección de un candidato para una solicitud de personal.
 */
class RecruitmentApplication extends Model
{
    /** @use HasFactory<RecruitmentApplicationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'recruitment_request_id',
        'recruitment_candidate_id',
        'stage',
        'rating',
        'interview_at',
        'discard_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => RecruitmentStage::class,
            'rating' => 'integer',
            'interview_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RecruitmentRequest, $this>
     */
    public function recruitmentRequest(): BelongsTo
    {
        return $this->belongsTo(RecruitmentRequest::class);
    }

    /**
     * @return BelongsTo<RecruitmentCandidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'recruitment_candidate_id');
    }

    /**
     * @return HasMany<RecruitmentApplicationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(RecruitmentApplicationEvent::class);
    }
}
