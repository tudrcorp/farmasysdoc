<?php

namespace App\Models;

use Database\Factories\PlanAgeRangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rango de edad de un plan con su prima mensual por persona.
 */
class PlanAgeRange extends Model
{
    /** @use HasFactory<PlanAgeRangeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'min_age',
        'max_age',
        'monthly_premium_usd',
        'monthly_premium_ves',
        'sort',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_age' => 'integer',
            'max_age' => 'integer',
            'monthly_premium_usd' => 'decimal:2',
            'monthly_premium_ves' => 'decimal:2',
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

    public function label(): string
    {
        return $this->max_age === null
            ? "{$this->min_age} años en adelante"
            : "{$this->min_age} a {$this->max_age} años";
    }

    public function coversAge(int $age): bool
    {
        return $age >= $this->min_age && ($this->max_age === null || $age <= $this->max_age);
    }
}
