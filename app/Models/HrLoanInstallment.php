<?php

namespace App\Models;

use Database\Factories\HrLoanInstallmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLoanInstallment extends Model
{
    /** @use HasFactory<HrLoanInstallmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'hr_loan_id',
        'number',
        'amount_usd',
        'period_date',
        'payroll_line_id',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
            'period_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<HrLoan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(HrLoan::class, 'hr_loan_id');
    }

    /**
     * @return BelongsTo<PayrollLine, $this>
     */
    public function payrollLine(): BelongsTo
    {
        return $this->belongsTo(PayrollLine::class);
    }
}
