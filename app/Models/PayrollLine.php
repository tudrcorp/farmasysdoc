<?php

namespace App\Models;

use Database\Factories\PayrollLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollLine extends Model
{
    /** @use HasFactory<PayrollLineFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'base_salary_usd',
        'usd_cash_portion',
        'ves_portion_usd',
        'assignments_usd',
        'deductions_usd',
        'loans_usd',
        'net_usd',
        'base_salary_ves',
        'assignments_ves',
        'deductions_ves',
        'loans_ves',
        'net_ves',
        'cash_paid_usd',
        'cash_paid_ves',
        'bcv_ves_per_usd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_salary_usd' => 'decimal:2',
            'usd_cash_portion' => 'decimal:2',
            'ves_portion_usd' => 'decimal:2',
            'assignments_usd' => 'decimal:2',
            'deductions_usd' => 'decimal:2',
            'loans_usd' => 'decimal:2',
            'net_usd' => 'decimal:2',
            'base_salary_ves' => 'decimal:2',
            'assignments_ves' => 'decimal:2',
            'deductions_ves' => 'decimal:2',
            'loans_ves' => 'decimal:2',
            'net_ves' => 'decimal:2',
            'cash_paid_usd' => 'decimal:2',
            'cash_paid_ves' => 'decimal:2',
            'bcv_ves_per_usd' => 'decimal:6',
        ];
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<PayrollLineItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollLineItem::class);
    }
}
