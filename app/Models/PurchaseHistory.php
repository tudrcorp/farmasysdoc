<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseHistory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'entry_type',
        'purchase_id',
        'branch_id',
        'accounts_payable_id',
        'issued_at',
        'registered_in_system_date',
        'supplier_invoice_number',
        'supplier_control_number',
        'supplier_tax_id',
        'supplier_name',
        'purchase_total_usd',
        'purchase_total_ves_at_issue',
        'total_ves_at_system_registration',
        'payment_method',
        'payment_form',
        'paid_at',
        'amount_paid_ves',
        'amount_paid_usd',
        'bcv_rate_at_payment',
        'payment_reference',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'registered_in_system_date' => 'date',
            'purchase_total_usd' => 'decimal:2',
            'purchase_total_ves_at_issue' => 'decimal:2',
            'total_ves_at_system_registration' => 'decimal:2',
            'paid_at' => 'datetime',
            'amount_paid_ves' => 'decimal:2',
            'amount_paid_usd' => 'decimal:2',
            'bcv_rate_at_payment' => 'decimal:8',
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

    /**
     * @return BelongsTo<AccountsPayable, $this>
     */
    public function accountsPayable(): BelongsTo
    {
        return $this->belongsTo(AccountsPayable::class);
    }
}
