<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de aviso de vencimiento enviado (uno por convenio y umbral de días).
 */
class PartnerCompanyAgreementNotice extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'partner_company_agreement_id',
        'threshold_days',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'threshold_days' => 'integer',
            'sent_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<PartnerCompanyAgreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(PartnerCompanyAgreement::class, 'partner_company_agreement_id');
    }
}
