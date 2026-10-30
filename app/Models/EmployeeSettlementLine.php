<?php

namespace App\Models;

use App\Enums\EmployeeSettlementLineType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Concepto de una liquidación. `calculated_usd` conserva el resultado del sistema
 * aunque el monto se ajuste a mano.
 */
class EmployeeSettlementLine extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_settlement_id',
        'concept',
        'type',
        'label',
        'formula',
        'calculated_usd',
        'amount_usd',
        'adjustment_reason',
        'adjusted_by',
        'sort',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EmployeeSettlementLineType::class,
            'calculated_usd' => 'decimal:2',
            'amount_usd' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<EmployeeSettlement, $this>
     */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(EmployeeSettlement::class, 'employee_settlement_id');
    }

    public function isAdjusted(): bool
    {
        return filled($this->adjustment_reason);
    }
}
