<?php

namespace App\Models;

use App\Enums\HrPayCurrencyBucket;
use App\Enums\HrRecurrence;
use Database\Factories\HrDeductionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrDeduction extends Model
{
    /** @use HasFactory<HrDeductionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'concept',
        'amount_usd',
        'pay_currency_bucket',
        'recurrence',
        'applies_on',
        'starts_on',
        'ends_on',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
            'pay_currency_bucket' => HrPayCurrencyBucket::class,
            'recurrence' => HrRecurrence::class,
            'applies_on' => 'date',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
