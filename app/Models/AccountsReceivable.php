<?php

namespace App\Models;

use App\Support\Finance\AccountsReceivableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountsReceivable extends Model
{
    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => AccountsReceivableStatus::POR_COBRAR,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sale_id',
        'branch_id',
        'client_id',
        'status',
        'sale_number_snapshot',
        'client_name_snapshot',
        'client_document_snapshot',
        'issued_at',
        'due_at',
        'sale_total_usd',
        'paid_equivalent_usd',
        'remaining_principal_usd',
        'payment_usd_snapshot',
        'payment_ves_snapshot',
        'bcv_ves_per_usd_snapshot',
        'sale_total_ves_reference',
        'original_balance_ves',
        'current_balance_ves',
        'last_balance_recalculated_at',
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
            'issued_at' => 'date',
            'due_at' => 'date',
            'sale_total_usd' => 'decimal:2',
            'paid_equivalent_usd' => 'decimal:2',
            'remaining_principal_usd' => 'decimal:2',
            'payment_usd_snapshot' => 'decimal:2',
            'payment_ves_snapshot' => 'decimal:2',
            'bcv_ves_per_usd_snapshot' => 'decimal:6',
            'sale_total_ves_reference' => 'decimal:2',
            'original_balance_ves' => 'decimal:2',
            'current_balance_ves' => 'decimal:2',
            'last_balance_recalculated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
