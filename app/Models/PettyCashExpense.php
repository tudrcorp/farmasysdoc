<?php

namespace App\Models;

use App\Enums\PettyCashCurrency;
use Database\Factories\PettyCashExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashExpense extends Model
{
    /** @use HasFactory<PettyCashExpenseFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'petty_cash_fund_id',
        'spent_at',
        'description',
        'currency',
        'amount',
        'amount_usd',
        'amount_ves',
        'bcv_ves_per_usd',
        'receipt_path',
        'registered_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spent_at' => 'datetime',
            'currency' => PettyCashCurrency::class,
            'amount' => 'decimal:2',
            'amount_usd' => 'decimal:2',
            'amount_ves' => 'decimal:2',
            'bcv_ves_per_usd' => 'decimal:6',
        ];
    }

    /**
     * @return BelongsTo<PettyCashFund, $this>
     */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(PettyCashFund::class, 'petty_cash_fund_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
