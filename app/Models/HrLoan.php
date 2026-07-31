<?php

namespace App\Models;

use App\Enums\HrLoanFrequency;
use App\Enums\HrLoanInstallmentMode;
use App\Enums\HrLoanStatus;
use Database\Factories\HrLoanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrLoan extends Model
{
    /** @use HasFactory<HrLoanFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'branch_id',
        'concept',
        'amount_usd',
        'remaining_usd',
        'frequency',
        'installment_mode',
        'fixed_installment_usd',
        'installments_count',
        'salary_percentage',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
            'remaining_usd' => 'decimal:2',
            'fixed_installment_usd' => 'decimal:2',
            'salary_percentage' => 'decimal:2',
            'frequency' => HrLoanFrequency::class,
            'installment_mode' => HrLoanInstallmentMode::class,
            'status' => HrLoanStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<HrLoanInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(HrLoanInstallment::class);
    }
}
