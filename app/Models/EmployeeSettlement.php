<?php

namespace App\Models;

use App\Enums\EmployeeSettlementStatus;
use Database\Factories\EmployeeSettlementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Liquidación de un egreso. Guarda una copia de los datos usados en el cálculo:
 * cambiar después el sueldo o los parámetros no altera una liquidación ya hecha.
 */
class EmployeeSettlement extends Model
{
    /** @use HasFactory<EmployeeSettlementFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_exit_id',
        'status',
        'hired_at',
        'last_work_day',
        'service_label',
        'monthly_salary_usd',
        'normal_daily_usd',
        'integral_daily_usd',
        'parameters',
        'guarantee_accrued_usd',
        'accrued_interest_usd',
        'advances_usd',
        'utilities_paid_usd',
        'pending_vacation_days',
        'pending_bonus_days',
        'unpaid_salary_days',
        'total_assignments_usd',
        'total_deductions_usd',
        'net_usd',
        'bcv_ves_per_usd',
        'net_ves',
        'created_by',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
        'payment_method',
        'payment_reference',
        'cancel_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmployeeSettlementStatus::class,
            'hired_at' => 'date',
            'last_work_day' => 'date',
            'monthly_salary_usd' => 'decimal:2',
            'normal_daily_usd' => 'decimal:4',
            'integral_daily_usd' => 'decimal:4',
            'parameters' => 'array',
            'guarantee_accrued_usd' => 'decimal:2',
            'accrued_interest_usd' => 'decimal:2',
            'advances_usd' => 'decimal:2',
            'utilities_paid_usd' => 'decimal:2',
            'pending_vacation_days' => 'decimal:2',
            'pending_bonus_days' => 'decimal:2',
            'unpaid_salary_days' => 'decimal:2',
            'total_assignments_usd' => 'decimal:2',
            'total_deductions_usd' => 'decimal:2',
            'net_usd' => 'decimal:2',
            'bcv_ves_per_usd' => 'decimal:6',
            'net_ves' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EmployeeExit, $this>
     */
    public function employeeExit(): BelongsTo
    {
        return $this->belongsTo(EmployeeExit::class);
    }

    /**
     * @return HasMany<EmployeeSettlementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(EmployeeSettlementLine::class)->orderBy('sort');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function isDraft(): bool
    {
        return $this->status === EmployeeSettlementStatus::Draft;
    }
}
