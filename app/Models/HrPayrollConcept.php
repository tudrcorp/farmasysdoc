<?php

namespace App\Models;

use App\Enums\HrPayCurrencyBucket;
use App\Enums\HrPayrollConceptApplication;
use App\Enums\HrPayrollConceptBehavior;
use App\Enums\HrPayrollConceptCurrency;
use App\Enums\HrPayrollConceptType;
use App\Services\Hr\HrUsdVesConverter;
use Database\Factories\HrPayrollConceptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class HrPayrollConcept extends Model
{
    /** @use HasFactory<HrPayrollConceptFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'application',
        'behavior',
        'amount',
        'currency',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HrPayrollConceptType::class,
            'application' => HrPayrollConceptApplication::class,
            'behavior' => HrPayrollConceptBehavior::class,
            'amount' => 'decimal:2',
            'currency' => HrPayrollConceptCurrency::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (HrPayrollConcept $concept): void {
            $concept->syncLegalCurrency();
        });
    }

    public function syncLegalCurrency(): void
    {
        if ($this->application === HrPayrollConceptApplication::Legal) {
            $this->currency = HrPayrollConceptCurrency::Ves;
        }
    }

    public function isLegal(): bool
    {
        return $this->application === HrPayrollConceptApplication::Legal;
    }

    public function isFixed(): bool
    {
        return $this->behavior === HrPayrollConceptBehavior::Fixed;
    }

    public function isPercentage(): bool
    {
        return $this->behavior === HrPayrollConceptBehavior::Percentage;
    }

    public function appliesOnSelectedPeriods(): bool
    {
        return $this->application === HrPayrollConceptApplication::Business && $this->isPercentage();
    }

    /**
     * @param  Builder<HrPayrollConcept>  $query
     * @return Builder<HrPayrollConcept>
     */
    public function scopeActiveBusiness(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('application', HrPayrollConceptApplication::Business);
    }

    /**
     * @return Collection<int, HrPayrollConcept>
     */
    public static function applicableBusinessFor(PayrollPeriod $period): Collection
    {
        return self::query()
            ->activeBusiness()
            ->where(function (Builder $query) use ($period): void {
                $query
                    ->where(function (Builder $fixed): void {
                        $fixed
                            ->where('behavior', HrPayrollConceptBehavior::Fixed)
                            ->orWhereNull('behavior');
                    })
                    ->orWhere(function (Builder $percentage) use ($period): void {
                        $percentage
                            ->where('behavior', HrPayrollConceptBehavior::Percentage)
                            ->whereHas(
                                'payrollPeriods',
                                fn (Builder $periods): Builder => $periods->where('payroll_periods.id', $period->id),
                            );
                    });
            })
            ->orderBy('name')
            ->get();
    }

    public function appliesToPeriod(PayrollPeriod $period): bool
    {
        if (! $this->is_active || $this->application !== HrPayrollConceptApplication::Business) {
            return false;
        }

        if (! $this->appliesOnSelectedPeriods()) {
            return true;
        }

        if ($this->relationLoaded('payrollPeriods')) {
            return $this->payrollPeriods->contains(
                fn (PayrollPeriod $attached): bool => (int) $attached->id === (int) $period->id,
            );
        }

        return $this->payrollPeriods()
            ->where('payroll_periods.id', $period->id)
            ->exists();
    }

    public function resolveAmountUsd(Employee $employee, float $rate): float
    {
        if ($this->isPercentage()) {
            return round($employee->biweeklyBaseUsd() * ((float) $this->amount / 100), 2);
        }

        $amount = round((float) $this->amount, 2);

        if (($this->currency ?? HrPayrollConceptCurrency::Ves) === HrPayrollConceptCurrency::Ves) {
            return HrUsdVesConverter::toUsd($amount, $rate);
        }

        return $amount;
    }

    public function payCurrencyBucket(): HrPayCurrencyBucket
    {
        if ($this->isPercentage()) {
            return HrPayCurrencyBucket::Ves;
        }

        return ($this->currency ?? HrPayrollConceptCurrency::Ves) === HrPayrollConceptCurrency::Usd
            ? HrPayCurrencyBucket::Usd
            : HrPayCurrencyBucket::Ves;
    }

    /**
     * @return BelongsToMany<PayrollPeriod, $this>
     */
    public function payrollPeriods(): BelongsToMany
    {
        return $this->belongsToMany(PayrollPeriod::class, 'hr_payroll_concept_payroll_period')
            ->withTimestamps();
    }

    /**
     * @return list<int>
     */
    public function futurePayrollPeriodIds(): array
    {
        return $this->payrollPeriods()
            ->upcoming()
            ->pluck('payroll_periods.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int|string>  $futurePeriodIds
     */
    public function syncApplicablePayrollPeriods(array $futurePeriodIds): void
    {
        if (! $this->appliesOnSelectedPeriods()) {
            $this->payrollPeriods()->detach();

            return;
        }

        $allowedFutureIds = PayrollPeriod::query()
            ->upcoming()
            ->whereIn('id', $futurePeriodIds)
            ->pluck('id');

        $pastIds = $this->payrollPeriods()
            ->whereDate('payroll_periods.period_date', '<', now()->toDateString())
            ->pluck('payroll_periods.id');

        $this->payrollPeriods()->sync(
            $pastIds->merge($allowedFutureIds)->unique()->values()->all(),
        );
    }

    public function formattedAmount(): string
    {
        if ($this->isPercentage()) {
            return number_format((float) $this->amount, 2, ',', '.').' %';
        }

        $currency = $this->currency ?? HrPayrollConceptCurrency::Ves;

        return $currency->prefix().' '.number_format((float) $this->amount, 2, ',', '.');
    }
}
