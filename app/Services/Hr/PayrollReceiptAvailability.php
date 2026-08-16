<?php

namespace App\Services\Hr;

use App\Enums\PayrollPeriodStatus;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class PayrollReceiptAvailability
{
    public function isFutureMonth(int $year, int $month, ?CarbonInterface $today = null): bool
    {
        $today ??= now();

        return $year > $today->year
            || ($year === $today->year && $month > $today->month);
    }

    public function isAvailable(int $year, int $month, ?CarbonInterface $today = null): bool
    {
        $today ??= now();

        if ($this->isFutureMonth($year, $month, $today)) {
            return false;
        }

        if ($this->bothPeriodsAreReady($year, $month)) {
            return true;
        }

        return $today->copy()->startOfDay()->gte($this->releaseDate($year, $month));
    }

    public function bothPeriodsAreReady(int $year, int $month): bool
    {
        $periods = PayrollPeriod::query()
            ->whereYear('period_date', $year)
            ->whereMonth('period_date', $month)
            ->get();

        if ($periods->count() < 2) {
            return false;
        }

        return $periods->every(
            fn (PayrollPeriod $period): bool => in_array($period->status, [
                PayrollPeriodStatus::Calculated,
                PayrollPeriodStatus::Closed,
            ], true),
        );
    }

    public function releaseDate(int $year, int $month): CarbonInterface
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();

        return $start->day(min(30, $start->daysInMonth));
    }

    /**
     * @return list<array{year: int, month: int}>
     */
    public function availableMonthsFor(Employee $employee, ?CarbonInterface $today = null): array
    {
        $today ??= now();
        $employeeStart = $employee->created_at?->copy()->startOfMonth();

        $slots = PayrollPeriod::query()
            ->get(['period_date'])
            ->map(fn (PayrollPeriod $period): string => $period->period_date->format('Y-n'))
            ->unique()
            ->values();

        $currentKey = $today->format('Y-n');
        if (! $slots->contains($currentKey)) {
            $slots->push($currentKey);
        }

        return $slots
            ->map(function (string $key): array {
                [$year, $month] = array_map('intval', explode('-', $key));

                return ['year' => $year, 'month' => $month];
            })
            ->filter(function (array $slot) use ($today, $employeeStart): bool {
                $slotDate = Carbon::create($slot['year'], $slot['month'], 1)->startOfMonth();

                if ($employeeStart !== null && $slotDate->lt($employeeStart)) {
                    return false;
                }

                return $this->isAvailable($slot['year'], $slot['month'], $today);
            })
            ->sortBy([
                ['year', 'asc'],
                ['month', 'asc'],
            ])
            ->values()
            ->all();
    }
}
