<?php

namespace App\Models;

use App\Enums\FinancialFixedExpenseCategory;
use App\Enums\PettyCashCurrency;
use Database\Factories\FinancialFixedExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialFixedExpense extends Model
{
    /** @use HasFactory<FinancialFixedExpenseFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'paid_at',
        'category',
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
            'paid_at' => 'datetime',
            'category' => FinancialFixedExpenseCategory::class,
            'currency' => PettyCashCurrency::class,
            'amount' => 'decimal:2',
            'amount_usd' => 'decimal:2',
            'amount_ves' => 'decimal:2',
            'bcv_ves_per_usd' => 'decimal:6',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
