<?php

namespace App\Models;

use Database\Factories\PlanSubplanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Subplan de un plan por cantidad de afiliados (ej. Plan Agudos / Plan Integral de Cápita).
 * Hereda los servicios y catálogos del plan base y agrega los suyos.
 */
class PlanSubplan extends Model
{
    /** @use HasFactory<PlanSubplanFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'uuid',
        'name',
        'description',
        'sort',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PlanSubplan $subplan): void {
            $subplan->uuid ??= (string) Str::uuid();
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
     * Servicios propios del subplan (sin los heredados del plan base).
     *
     * @return BelongsToMany<PlanService, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(PlanService::class)->withTimestamps();
    }

    /**
     * Catálogos propios del subplan (sin los heredados del plan base).
     *
     * @return BelongsToMany<Formulary, $this>
     */
    public function formularies(): BelongsToMany
    {
        return $this->belongsToMany(Formulary::class)->withTimestamps();
    }

    /**
     * @return HasMany<PlanAffiliateBandPrice, $this>
     */
    public function bandPrices(): HasMany
    {
        return $this->hasMany(PlanAffiliateBandPrice::class);
    }

    /**
     * @return HasMany<PartnerCompanyPlan, $this>
     */
    public function partnerAssignments(): HasMany
    {
        return $this->hasMany(PartnerCompanyPlan::class);
    }

    /**
     * Servicios del plan base más los propios, sin repetir.
     *
     * @return Collection<int, PlanService>
     */
    public function effectiveServices(): Collection
    {
        return $this->plan->services->concat($this->services)->unique('id')->sortBy('name')->values();
    }

    /**
     * Catálogos del plan base más los propios, sin repetir.
     *
     * @return Collection<int, Formulary>
     */
    public function effectiveFormularies(): Collection
    {
        return $this->plan->formularies->concat($this->formularies)->unique('id')->sortBy('name')->values();
    }
}
