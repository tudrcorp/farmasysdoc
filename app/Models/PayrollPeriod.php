<?php

namespace App\Models;

use App\Enums\PayrollPeriodStatus;
use App\Services\Hr\PayrollPeriodVisibility;
use Carbon\CarbonInterface;
use Database\Factories\PayrollPeriodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    public function visibilityStartsOn(): CarbonInterface
    {
        return app(PayrollPeriodVisibility::class)->windowStart($this);
    }

    public function visibilityEndsOn(): CarbonInterface
    {
        return app(PayrollPeriodVisibility::class)->windowEnd($this);
    }

    /**
     * @param  Builder<PayrollPeriod>  $query
     * @return Builder<PayrollPeriod>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query
            ->whereDate('period_date', '>=', now()->toDateString())
            ->orderBy('period_date');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function upcomingGroupedOptions(): array
    {
        $grouped = [];

        self::query()
            ->upcoming()
            ->orderBy('year')
            ->orderBy('period_number')
            ->get()
            ->each(function (PayrollPeriod $period) use (&$grouped): void {
                $grouped[(string) $period->year][$period->id] = sprintf(
                    '%s · %s · %s',
                    $period->halfLabel(),
                    Str::ucfirst($period->period_date->locale('es')->translatedFormat('F')),
                    $period->period_date->format('d/m/Y'),
                );
            });

        return $grouped;
    }

    /**
     * @return HasMany<PayrollLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    /**
     * @return BelongsToMany<HrPayrollConcept, $this>
     */
    public function payrollConcepts(): BelongsToMany
    {
        return $this->belongsToMany(HrPayrollConcept::class, 'hr_payroll_concept_payroll_period')
            ->withTimestamps();
    }
}
