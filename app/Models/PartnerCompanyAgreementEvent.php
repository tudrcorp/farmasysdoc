<?php

namespace App\Models;

use App\Enums\PartnerAgreementEventType;
use App\Enums\PartnerAgreementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora inmutable de un convenio (quién, cuándo, de qué estado a cuál y por qué).
 */
class PartnerCompanyAgreementEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'partner_company_agreement_id',
        'type',
        'from_status',
        'to_status',
        'user_id',
        'notes',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PartnerAgreementEventType::class,
            'from_status' => PartnerAgreementStatus::class,
            'to_status' => PartnerAgreementStatus::class,
            'meta' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<PartnerCompanyAgreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(PartnerCompanyAgreement::class, 'partner_company_agreement_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actorLabel(): string
    {
        return $this->user?->name ?? 'Sistema';
    }
}
