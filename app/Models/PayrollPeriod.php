<?php

namespace App\Models;

use App\Enums\PayrollPeriodStatus;
use Database\Factories\PayrollPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    /** @use HasFactory<PayrollPeriodFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'period_date',
        'year',
        'period_number',
        'bcv_ves_per_usd',
        'status',
        'total_assignments_usd',
        'total_assignments_ves',
        'total_deductions_usd',
        'total_deductions_ves',
        'total_loans_usd',
        'total_loans_ves',
        'total_payable_usd',
        'total_payable_ves',
        'calculated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'bcv_ves_per_usd' => 'decimal:6',
            'status' => PayrollPeriodStatus::class,
            'total_assignments_usd' => 'decimal:2',
            'total_assignments_ves' => 'decimal:2',
            'total_deductions_usd' => 'decimal:2',
            'total_deductions_ves' => 'decimal:2',
            'total_loans_usd' => 'decimal:2',
            'total_loans_ves' => 'decimal:2',
            'total_payable_usd' => 'decimal:2',
            'total_payable_ves' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function isMonthEnd(): bool
    {
        return $this->period_date->day === $this->period_date->copy()->endOfMonth()->day;
    }

    public function halfLabel(): string
    {
        return $this->isMonthEnd() ? '2.ª quincena' : '1.ª quincena';
    }

    public function monthLabel(): string
    {
        return $this->period_date->locale('es')->translatedFormat('F Y');
    }

    public function label(): string
    {
        return sprintf(
            'Periodo %d — %s',
            $this->period_number,
            $this->period_date->format('d/m/Y'),
        );
    }

    /**
     * @return HasMany<PayrollLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }
}
