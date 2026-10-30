<?php

namespace App\Models;

use App\Enums\RecruitmentStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial del proceso: cambios de etapa y notas del analista.
 */
class RecruitmentApplicationEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'recruitment_application_id',
        'user_id',
        'from_stage',
        'to_stage',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_stage' => RecruitmentStage::class,
            'to_stage' => RecruitmentStage::class,
        ];
    }

    /**
     * @return BelongsTo<RecruitmentApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
