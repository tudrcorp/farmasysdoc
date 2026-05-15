<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento de la caja física del cajero (conciliación de vueltos, etc.).
 *
 * @property-read PhysicalCashBox $physicalCashBox
 * @property-read Sale $sale
 */
class PhysicalCashBoxMovement extends Model
{
    protected $table = 'cajas_fisicas_movimientos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'physical_cash_box_id',
        'sale_id',
        'kind',
        'client_bill_usd',
        'document_total_usd',
        'change_on_bill_usd',
        'change_on_bill_ves',
        'drawer_out_usd',
        'final_change_usd',
        'final_change_ves',
        'bcv_ves_per_usd',
        'meta',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client_bill_usd' => 'decimal:2',
            'document_total_usd' => 'decimal:2',
            'change_on_bill_usd' => 'decimal:2',
            'change_on_bill_ves' => 'decimal:2',
            'drawer_out_usd' => 'decimal:2',
            'final_change_usd' => 'decimal:2',
            'final_change_ves' => 'decimal:2',
            'bcv_ves_per_usd' => 'decimal:6',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PhysicalCashBox, $this>
     */
    public function physicalCashBox(): BelongsTo
    {
        return $this->belongsTo(PhysicalCashBox::class, 'physical_cash_box_id');
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
