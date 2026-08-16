<?php

namespace App\Services\Hr;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PayrollPeriodVisibility
{
    public const int GRACE_DAYS = 5;

    public function today(): CarbonInterface
    {
        return now()->startOfDay();
    }

    public function windowStart(PayrollPeriod $period): CarbonInterface
    {
        $payment = $period->period_date->copy()->startOfDay();

        if ($period->isMonthEnd()) {
            return $payment->copy()->day(15);
        }

        return $payment->copy()->startOfMonth();
    }

    public function windowEnd(PayrollPeriod $period): CarbonInterface
    {
        return $period->period_date->copy()->startOfDay()->addDays(self::GRACE_DAYS);
    }

    public function isVisible(PayrollPeriod $period, ?CarbonInterface $on = null): bool
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();

        return $on->betweenIncluded($this->windowStart($period), $this->windowEnd($period));
    }

    public function isCurrent(PayrollPeriod $period, ?CarbonInterface $on = null): bool
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();
        $payment = $period->period_date->copy()->startOfDay();

        return $this->isVisible($period, $on) && $on->lte($payment);
    }

    public function isOverdue(PayrollPeriod $period, ?CarbonInterface $on = null): bool
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();
        $payment = $period->period_date->copy()->startOfDay();

        return $this->isVisible($period, $on) && $on->gt($payment);
    }

    /**
     * Días que restan de visibilidad tras la fecha de pago (0 = último día).
     * Null si el periodo aún no vence o ya no está visible.
     */
    public function remainingVisibilityDays(PayrollPeriod $period, ?CarbonInterface $on = null): ?int
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();

        if (! $this->isOverdue($period, $on) && ! $this->isPaymentDay($period, $on)) {
            return null;
        }

        if (! $this->isVisible($period, $on)) {
            return null;
        }

        return (int) $on->diffInDays($this->windowEnd($period), false);
    }

    public function isPaymentDay(PayrollPeriod $period, ?CarbonInterface $on = null): bool
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();

        return $on->equalTo($period->period_date->copy()->startOfDay());
    }

    /**
     * @return Collection<int, PayrollPeriod>
     */
    public function visiblePeriods(?CarbonInterface $on = null): Collection
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();
        $ids = $this->visiblePeriodIds($on);

        if ($ids === []) {
            return collect();
        }

        return PayrollPeriod::query()
            ->whereIn('id', $ids)
            ->orderBy('period_date')
            ->get();
    }

    /**
     * @return list<int>
     */
    public function visiblePeriodIds(?CarbonInterface $on = null): array
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();
        $from = $on->copy()->startOfMonth()->subDays(self::GRACE_DAYS);
        $to = $on->copy()->endOfMonth();

        return PayrollPeriod::query()
            ->whereDate('period_date', '>=', $from->toDateString())
            ->whereDate('period_date', '<=', $to->toDateString())
            ->orderBy('period_date')
            ->get()
            ->filter(fn (PayrollPeriod $period): bool => $this->isVisible($period, $on))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function constrain(Builder $query, ?CarbonInterface $on = null): Builder
    {
        $ids = $this->visiblePeriodIds($on);

        return $query->whereIn('id', $ids);
    }

    public function constrainPending(Builder $query, ?CarbonInterface $on = null): Builder
    {
        return $this->constrain($query, $on)
            ->where('status', '!=', PayrollPeriodStatus::Calculated->value);
    }

    /**
     * @param  list<string>  $statuses
     */
    public function constrainList(Builder $query, ?int $consultPeriodId = null, array $statuses = []): Builder
    {
        $visibleIds = $this->visiblePeriodIds();
        $key = $query->getModel()->getQualifiedKeyName();

        return $query->where(function (Builder $builder) use ($visibleIds, $consultPeriodId, $statuses, $key): void {
            $builder->whereIn('id', $visibleIds);

            if ($statuses !== []) {
                $builder->whereIn('status', $statuses);
            } else {
                $builder->where('status', '!=', PayrollPeriodStatus::Calculated->value);
            }

            if ($consultPeriodId !== null && $consultPeriodId > 0) {
                $builder->orWhere($key, $consultPeriodId);
            }
        });
    }

    public function overduePeriod(?CarbonInterface $on = null): ?PayrollPeriod
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();

        return $this->visiblePeriods($on)
            ->first(function (PayrollPeriod $period) use ($on): bool {
                if ($period->status !== PayrollPeriodStatus::Draft) {
                    return false;
                }

                return $this->isOverdue($period, $on);
            });
    }

    /**
     * @return array{label: string, color: string, description: string}
     */
    public function tableState(PayrollPeriod $period, ?CarbonInterface $on = null): array
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();
        $until = $this->windowEnd($period)->format('d/m/Y');

        if ($this->isOverdue($period, $on)) {
            $remaining = $this->remainingVisibilityDays($period, $on) ?? 0;
            $label = $remaining === 0
                ? 'Atrasado · último día'
                : 'Atrasado · '.$remaining.' '.$this->daysWord($remaining);

            return [
                'label' => $label,
                'color' => 'warning',
                'description' => 'Visible hasta el '.$until,
            ];
        }

        if ($this->isPaymentDay($period, $on)) {
            $remaining = $this->remainingVisibilityDays($period, $on) ?? self::GRACE_DAYS;

            return [
                'label' => 'Día de pago · '.$remaining.' '.$this->daysWord($remaining).' de visibilidad',
                'color' => 'info',
                'description' => 'Seguirá visible hasta el '.$until,
            ];
        }

        return [
            'label' => 'Vigente',
            'color' => 'success',
            'description' => 'Pago el '.$period->period_date->format('d/m/Y').' · visible hasta el '.$until,
        ];
    }

    private function daysWord(int $days): string
    {
        return $days === 1 ? 'día' : 'días';
    }
}
