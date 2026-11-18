<?php

namespace App\Models;

use App\Enums\PlanPremiumMode;
use App\Enums\PlanStatus;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan corporativo: servicios, prima mensual por persona (única o por rango de edad),
 * catálogos de principios activos y compañías aliadas que lo tienen.
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'premium_mode',
        'has_subplans',
        'monthly_premium_usd',
        'monthly_premium_ves',
        'valid_from',
        'valid_until',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'premium_mode' => PlanPremiumMode::class,
            'has_subplans' => 'boolean',
            'monthly_premium_usd' => 'decimal:2',
            'monthly_premium_ves' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    /**
     * @return BelongsToMany<PlanService, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(PlanService::class)->withTimestamps();
    }

    /**
     * @return HasMany<PlanAgeRange, $this>
     */
    public function ageRanges(): HasMany
    {
        return $this->hasMany(PlanAgeRange::class)->orderBy('min_age');
    }

    /**
     * @return BelongsToMany<Formulary, $this>
     */
    public function formularies(): BelongsToMany
    {
        return $this->belongsToMany(Formulary::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<PartnerCompany, $this>
     */
    public function partnerCompanies(): BelongsToMany
    {
        return $this->belongsToMany(PartnerCompany::class)
            ->using(PartnerCompanyPlan::class)
            ->withPivot(PartnerCompanyPlan::PIVOT_COLUMNS)
            ->withTimestamps();
    }

    /**
     * Subplanes (solo en la modalidad por cantidad de afiliados con subplanes).
     *
     * @return HasMany<PlanSubplan, $this>
     */
    public function subplans(): HasMany
    {
        return $this->hasMany(PlanSubplan::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Tramos de cantidad de afiliados del baremo.
     *
     * @return HasMany<PlanAffiliateBand, $this>
     */
    public function affiliateBands(): HasMany
    {
        return $this->hasMany(PlanAffiliateBand::class)->orderBy('min_affiliates');
    }

    /**
     * Asignaciones del plan a aliados (con monto autorizado y porcentajes en fondo de consumo).
     *
     * @return HasMany<PartnerCompanyPlan, $this>
     */
    public function partnerAssignments(): HasMany
    {
        return $this->hasMany(PartnerCompanyPlan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function usesAgeRanges(): bool
    {
        return $this->premium_mode === PlanPremiumMode::AgeRanges;
    }

    public function usesFlatPremium(): bool
    {
        return $this->premium_mode === PlanPremiumMode::Flat;
    }

    public function isRetail(): bool
    {
        return $this->premium_mode === PlanPremiumMode::Retail;
    }

    public function isConsumptionFund(): bool
    {
        return $this->premium_mode === PlanPremiumMode::ConsumptionFund;
    }

    public function usesAffiliateBands(): bool
    {
        return $this->premium_mode === PlanPremiumMode::AffiliateBands;
    }

    public function usesSubplans(): bool
    {
        return $this->usesAffiliateBands() && $this->has_subplans;
    }

    /**
     * Tramo del baremo que cubre la cantidad de afiliados indicada.
     */
    public function affiliateBandFor(int $affiliates): ?PlanAffiliateBand
    {
        if (! $this->usesAffiliateBands()) {
            return null;
        }

        return $this->affiliateBands->first(fn (PlanAffiliateBand $band): bool => $band->coversCount($affiliates));
    }

    /**
     * Tarifa en USD por afiliado al mes según la cantidad de afiliados y el subplan.
     *
     * @return array{band: PlanAffiliateBand, rate_usd: float}|null
     */
    public function affiliateRateFor(int $affiliates, ?PlanSubplan $subplan = null): ?array
    {
        $band = $this->affiliateBandFor($affiliates);
        $rate = $band?->priceFor($this->usesSubplans() ? $subplan : null);

        return $band === null || $rate === null ? null : ['band' => $band, 'rate_usd' => $rate];
    }

    /**
     * Rango de edad que aplica a la edad indicada (solo si el plan usa rangos).
     */
    public function ageRangeFor(int $age): ?PlanAgeRange
    {
        if (! $this->usesAgeRanges()) {
            return null;
        }

        return $this->ageRanges->first(fn (PlanAgeRange $range): bool => $range->coversAge($age));
    }

    /**
     * Prima mensual por persona según la edad: tarifa única o la del rango que la cubre (retail no tiene prima).
     *
     * @return array{usd: float, ves: float}|null
     */
    public function monthlyPremiumFor(?int $age = null): ?array
    {
        if ($this->isRetail() || $this->isConsumptionFund() || $this->usesAffiliateBands()) {
            return null;
        }

        if ($this->usesFlatPremium()) {
            if ($this->monthly_premium_usd === null) {
                return null;
            }

            return ['usd' => (float) $this->monthly_premium_usd, 'ves' => (float) $this->monthly_premium_ves];
        }

        $range = $age === null ? null : $this->ageRangeFor($age);

        return $range === null
            ? null
            : ['usd' => (float) $range->monthly_premium_usd, 'ves' => (float) $range->monthly_premium_ves];
    }

    /**
     * Principios activos distintos cubiertos por todos los catálogos del plan.
     */
    public function coveredActiveIngredientsCount(): int
    {
        return ActiveIngredient::query()
            ->whereHas('formularyCategories.formularies.plans', fn ($query) => $query->whereKey($this->getKey()))
            ->count();
    }

    /**
     * Resumen legible de la prima mensual en USD: tarifa única, rango mínimo–máximo o «Sin prima» (retail).
     */
    public function premiumSummary(): string
    {
        if ($this->isRetail()) {
            return 'Sin prima';
        }

        if ($this->isConsumptionFund()) {
            return 'Fondo de consumo';
        }

        if ($this->usesAffiliateBands()) {
            $prices = $this->affiliateBands->flatMap(fn (PlanAffiliateBand $band) => $band->prices->pluck('price_usd'))->map(fn (mixed $price): float => (float) $price);

            if ($prices->isEmpty()) {
                return 'Sin baremo cargado';
            }

            $min = $prices->min();
            $max = $prices->max();

            return $min === $max
                ? '$'.number_format($min, 2, ',', '.').' por afiliado'
                : '$'.number_format($min, 2, ',', '.').' – $'.number_format($max, 2, ',', '.').' por afiliado';
        }

        if ($this->usesFlatPremium()) {
            return $this->monthly_premium_usd === null
                ? 'Se define en el convenio'
                : '$'.number_format((float) $this->monthly_premium_usd, 2, ',', '.');
        }

        if ($this->ageRanges->isEmpty()) {
            return 'Sin rangos cargados';
        }

        $min = (float) $this->ageRanges->min('monthly_premium_usd');
        $max = (float) $this->ageRanges->max('monthly_premium_usd');

        return $min === $max
            ? '$'.number_format($min, 2, ',', '.')
            : '$'.number_format($min, 2, ',', '.').' – $'.number_format($max, 2, ',', '.');
    }

    public static function nextCode(): string
    {
        return 'PLN-'.str_pad((string) ((int) static::query()->max('id') + 1), 4, '0', STR_PAD_LEFT);
    }
}
