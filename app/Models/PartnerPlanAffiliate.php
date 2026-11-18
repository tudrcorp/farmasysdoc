<?php

namespace App\Models;

use App\Enums\PartnerAffiliateSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cliente afiliado a un plan de una compañía aliada (asignación `partner_company_plan`).
 * Un cliente puede estar afiliado a varios aliados y planes a la vez.
 */
class PartnerPlanAffiliate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'partner_company_plan_id',
        'partner_company_id',
        'plan_id',
        'client_id',
        'is_active',
        'source',
        'validated_with',
        'notes',
        'enrolled_by',
        'branch_id',
        'enrolled_at',
        'deactivated_at',
        'deactivated_by',
        'deactivated_via',
        'deactivation_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'source' => PartnerAffiliateSource::class,
            'enrolled_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<PartnerPlanAffiliate>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<PartnerCompanyPlan, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PartnerCompanyPlan::class, 'partner_company_plan_id');
    }

    /**
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * @return HasMany<PartnerAgreementSale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(PartnerAgreementSale::class);
    }
}
