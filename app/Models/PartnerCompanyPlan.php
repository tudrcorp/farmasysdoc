<?php

namespace App\Models;

use Database\Factories\PartnerCompanyPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Asignación de un plan a una compañía aliada. En los planes de fondo de consumo guarda
 * el monto de consumo autorizado y los porcentajes de ganancia (intermediario y aliado)
 * que la negociación decida usar.
 */
class PartnerCompanyPlan extends Pivot
{
    /** @use HasFactory<PartnerCompanyPlanFactory> */
    use HasFactory;

    protected $table = 'partner_company_plan';

    public $incrementing = true;

    /**
     * Columnas extra de la pivote que se exponen en las relaciones plan ↔ aliado.
     *
     * @var list<string>
     */
    public const PIVOT_COLUMNS = [
        'id',
        'plan_subplan_id',
        'affiliates_count',
        'agreed_rate_usd',
        'agreed_band_label',
        'authorized_amount_usd',
        'authorized_amount_ves',
        'applies_intermediary_profit',
        'intermediary_profit_percent',
        'applies_partner_profit',
        'partner_profit_percent',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'partner_company_id',
        'plan_subplan_id',
        'affiliates_count',
        'agreed_rate_usd',
        'agreed_band_label',
        'authorized_amount_usd',
        'authorized_amount_ves',
        'applies_intermediary_profit',
        'intermediary_profit_percent',
        'applies_partner_profit',
        'partner_profit_percent',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'affiliates_count' => 'integer',
            'agreed_rate_usd' => 'decimal:2',
            'authorized_amount_usd' => 'decimal:2',
            'authorized_amount_ves' => 'decimal:2',
            'applies_intermediary_profit' => 'boolean',
            'intermediary_profit_percent' => 'decimal:4',
            'applies_partner_profit' => 'boolean',
            'partner_profit_percent' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PartnerCompanyPlan $assignment): void {
            if (! $assignment->applies_intermediary_profit) {
                $assignment->intermediary_profit_percent = null;
            }

            if (! $assignment->applies_partner_profit) {
                $assignment->partner_profit_percent = null;
            }
        });
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }

    /**
     * @return BelongsTo<PlanSubplan, $this>
     */
    public function subplan(): BelongsTo
    {
        return $this->belongsTo(PlanSubplan::class, 'plan_subplan_id');
    }

    /**
     * @return HasMany<PartnerPlanAffiliate, $this>
     */
    public function affiliates(): HasMany
    {
        return $this->hasMany(PartnerPlanAffiliate::class, 'partner_company_plan_id');
    }

    /**
     * @return HasMany<PartnerFundMovement, $this>
     */
    public function fundMovements(): HasMany
    {
        return $this->hasMany(PartnerFundMovement::class, 'partner_company_plan_id');
    }

    /**
     * @return HasMany<PartnerAgreementSale, $this>
     */
    public function agreementSales(): HasMany
    {
        return $this->hasMany(PartnerAgreementSale::class, 'partner_company_plan_id');
    }

    /**
     * Disponible del fondo de consumo: monto autorizado más la suma con signo de los movimientos.
     */
    public function fundAvailableUsd(): float
    {
        return round((float) ($this->authorized_amount_usd ?? 0) + (float) $this->fundMovements()->sum('amount_usd'), 2);
    }

    /**
     * Total mensual en USD (afiliados × tarifa acordada) en planes por cantidad de afiliados.
     */
    public function monthlyTotalUsd(): ?float
    {
        if ($this->affiliates_count === null || $this->agreed_rate_usd === null) {
            return null;
        }

        return round($this->affiliates_count * (float) $this->agreed_rate_usd, 2);
    }

    /**
     * Texto de los porcentajes de ganancia que aplican, o null si ninguno.
     */
    public function profitSharingLabel(): ?string
    {
        $parts = [];

        if ($this->applies_intermediary_profit && $this->intermediary_profit_percent !== null) {
            $parts[] = 'Intermediario '.self::formatPercent($this->intermediary_profit_percent);
        }

        if ($this->applies_partner_profit && $this->partner_profit_percent !== null) {
            $parts[] = 'Aliado '.self::formatPercent($this->partner_profit_percent);
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    public static function formatPercent(mixed $percent): string
    {
        return rtrim(rtrim(number_format((float) $percent, 4, ',', '.'), '0'), ',').'%';
    }
}
