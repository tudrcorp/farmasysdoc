<?php

namespace App\Models;

use App\Support\Plans\PlanAffiliateBandRules;
use Database\Factories\PlanAffiliateBandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tramo de cantidad de afiliados del baremo de un plan (ej. 1.000 - 4.999).
 */
class PlanAffiliateBand extends Model
{
    /** @use HasFactory<PlanAffiliateBandFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'min_affiliates',
        'max_affiliates',
        'sort',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_affiliates' => 'integer',
            'max_affiliates' => 'integer',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<PlanAffiliateBandPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanAffiliateBandPrice::class);
    }

    public function coversCount(int $affiliates): bool
    {
        return $affiliates >= $this->min_affiliates
            && ($this->max_affiliates === null || $affiliates <= $this->max_affiliates);
    }

    public function label(): string
    {
        return PlanAffiliateBandRules::describe($this->min_affiliates, $this->max_affiliates);
    }

    /**
     * Precio en USD por afiliado al mes para el subplan (o la columna única si el plan no tiene subplanes).
     */
    public function priceFor(?PlanSubplan $subplan): ?float
    {
        $price = $this->prices->first(fn (PlanAffiliateBandPrice $price): bool => $price->plan_subplan_id === $subplan?->getKey());

        return $price === null ? null : (float) $price->price_usd;
    }
}
