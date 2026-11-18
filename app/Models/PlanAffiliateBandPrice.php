<?php

namespace App\Models;

use Database\Factories\PlanAffiliateBandPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Celda del baremo: precio en USD por afiliado al mes de un tramo para un subplan
 * (o para el plan completo cuando no maneja subplanes).
 */
class PlanAffiliateBandPrice extends Model
{
    /** @use HasFactory<PlanAffiliateBandPriceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_affiliate_band_id',
        'plan_subplan_id',
        'price_usd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_usd' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PlanAffiliateBand, $this>
     */
    public function band(): BelongsTo
    {
        return $this->belongsTo(PlanAffiliateBand::class, 'plan_affiliate_band_id');
    }

    /**
     * @return BelongsTo<PlanSubplan, $this>
     */
    public function subplan(): BelongsTo
    {
        return $this->belongsTo(PlanSubplan::class, 'plan_subplan_id');
    }
}
