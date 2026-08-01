<?php

namespace App\Models;

use App\Enums\HrPayCurrencyBucket;
use App\Enums\PayrollLineItemType;
use Database\Factories\PayrollLineItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PayrollLineItem extends Model
{
    /** @use HasFactory<PayrollLineItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payroll_line_id',
        'type',
        'reference_type',
        'reference_id',
        'concept',
        'amount_usd',
        'amount_ves',
        'pay_currency_bucket',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PayrollLineItemType::class,
            'amount_usd' => 'decimal:2',
            'amount_ves' => 'decimal:2',
            'pay_currency_bucket' => HrPayCurrencyBucket::class,
        ];
    }

    /**
     * @return BelongsTo<PayrollLine, $this>
     */
    public function payrollLine(): BelongsTo
    {
        return $this->belongsTo(PayrollLine::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
