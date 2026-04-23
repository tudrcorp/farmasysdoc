<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountsPayable extends Model
{
    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'por_pagar',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_id',
        'branch_id',
        'status',
        'issued_at',
        'due_at',
        'paid_at',
        'payment_reference',
        'supplier_invoice_number',
        'supplier_control_number',
        'supplier_tax_id',
        'supplier_name',
        'purchase_total_usd',
        'remaining_principal_usd',
        'purchase_total_ves_at_issue',
        'original_balance_ves',
        'current_balance_ves',
        'last_balance_recalculated_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'due_at' => 'date',
            'paid_at' => 'datetime',
            'purchase_total_usd' => 'decimal:2',
            'remaining_principal_usd' => 'decimal:2',
            'purchase_total_ves_at_issue' => 'decimal:2',
            'original_balance_ves' => 'decimal:2',
            'current_balance_ves' => 'decimal:2',
            'last_balance_recalculated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
