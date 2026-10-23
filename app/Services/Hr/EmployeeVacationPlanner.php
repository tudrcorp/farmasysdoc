<?php

namespace App\Services\Hr;

use App\Enums\EmployeeRequestStatus;
use App\Enums\EmployeeRequestType;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Período vacacional según la LOTTT: el derecho nace en cada aniversario de ingreso,
 * 15 días hábiles el primer año más 1 día por cada año adicional (hasta 30).
 * El mes sugerido es el del aniversario del año en curso (o del siguiente, si ya pasó).
 * Los días hábiles se cuentan de lunes a viernes.
 */
final class EmployeeVacationPlanner
{
    public const int BASE_DAYS = 15;

    public const int MAX_DAYS = 30;

    /**
     * @return array{
     *     service_year: int,
     *     right_starts_on: CarbonImmutable,
     *     right_is_born: bool,
     *     entitled_days: int,
     *     suggested_starts_on: CarbonImmutable,
     *     suggested_ends_on: CarbonImmutable,
     *     month_label: string,
     * }|null
     */
    public function forEmployee(Employee $employee, ?CarbonInterface $today = null): ?array
    {
        if ($employee->hired_at === null) {
            return null;
        }

        $today = CarbonImmutable::parse($today ?? now())->startOfDay();
        $hiredAt = CarbonImmutable::parse($employee->hired_at)->startOfDay();
        $serviceYear = max(1, $today->year - $hiredAt->year);

        if ($this->shouldMoveToNextYear($employee, $hiredAt->addYears($serviceYear), $today)) {
            $serviceYear++;
        }

        $rightStartsOn = $hiredAt->addYears($serviceYear);
        $entitledDays = $this->entitledDaysForServiceYear($serviceYear);
        $suggestedStartsOn = $this->nextBusinessDay($rightStartsOn->greaterThan($today) ? $rightStartsOn : $today->addDay());

        return [
            'service_year' => $serviceYear,
            'right_starts_on' => $rightStartsOn,
            'right_is_born' => $rightStartsOn->lessThanOrEqualTo($today),
            'entitled_days' => $entitledDays,
            'suggested_starts_on' => $suggestedStartsOn,
            'suggested_ends_on' => $this->addBusinessDays($suggestedStartsOn, $entitledDays),
            'month_label' => ucfirst($suggestedStartsOn->locale('es')->translatedFormat('F Y')),
        ];
    }

    public function entitledDaysForServiceYear(int $serviceYear): int
    {
        return min(self::MAX_DAYS, self::BASE_DAYS + max(0, $serviceYear - 1));
    }

    public function businessDaysBetween(CarbonInterface $startsOn, CarbonInterface $endsOn): int
    {
        $day = CarbonImmutable::parse($startsOn)->startOfDay();
        $end = CarbonImmutable::parse($endsOn)->startOfDay();
        $count = 0;

        while ($day->lessThanOrEqualTo($end)) {
            if (! $day->isWeekend()) {
                $count++;
            }

            $day = $day->addDay();
        }

        return $count;
    }

    /**
     * Último día del período que cubre `$days` días hábiles empezando en `$startsOn`.
     */
    public function addBusinessDays(CarbonImmutable $startsOn, int $days): CarbonImmutable
    {
        $day = $this->nextBusinessDay($startsOn);
        $counted = 1;

        while ($counted < $days) {
            $day = $day->addDay();

            if (! $day->isWeekend()) {
                $counted++;
            }
        }

        return $day;
    }

    private function nextBusinessDay(CarbonImmutable $day): CarbonImmutable
    {
        while ($day->isWeekend()) {
            $day = $day->addDay();
        }

        return $day;
    }

    /**
     * El período sugerido es el aniversario de este año. Pasa al siguiente si ya hay una solicitud
     * vigente para ese aniversario o si este ya quedó atrás hace más de un mes.
     */
    private function shouldMoveToNextYear(Employee $employee, CarbonImmutable $anniversary, CarbonImmutable $today): bool
    {
        return $anniversary->lessThan($today->subMonth())
            || $this->hasActiveRequestForServiceYear($employee, $anniversary);
    }

    private function hasActiveRequestForServiceYear(Employee $employee, CarbonImmutable $rightStartsOn): bool
    {
        return $employee->employeeRequests()
            ->where('type', EmployeeRequestType::Vacation)
            ->whereIn('status', EmployeeRequestStatus::active())
            ->whereDate('entitlement_starts_on', $rightStartsOn->toDateString())
            ->exists();
    }
}
