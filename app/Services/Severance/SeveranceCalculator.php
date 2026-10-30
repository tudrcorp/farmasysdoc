<?php

namespace App\Services\Severance;

use App\Enums\EmployeeExitReason;
use App\Enums\EmployeeSettlementLineType;
use Carbon\CarbonImmutable;

/**
 * Cálculo de la liquidación según la LOTTT, sobre el sueldo integral en USD.
 * No toca la base de datos: recibe los datos y devuelve cada concepto con su fórmula.
 *
 * - Prestaciones (art. 142): el mayor entre la garantía acumulada y 30 días por año
 *   o fracción superior a 6 meses al último salario integral. Si la relación duró
 *   menos de 3 meses, 5 días por mes o fracción.
 * - Indemnización (art. 92): monto igual a las prestaciones, por despido injustificado.
 * - Salario integral (art. 122): salario normal más alícuotas de utilidades y bono vacacional.
 * - Vacaciones y bono (arts. 190 a 196) y utilidades (arts. 131 y 132): fraccionados por mes completo.
 */
final class SeveranceCalculator
{
    /**
     * @param  array{utilities_days_per_year: int, vacation_base_days: int, vacation_max_days: int, vacation_bonus_base_days: int, vacation_bonus_max_days: int}  $parameters
     * @param  array{guarantee_accrued_usd: float, accrued_interest_usd: float, advances_usd: float, utilities_paid_usd: float, pending_vacation_days: float, pending_bonus_days: float, unpaid_salary_days: float, loans_usd: float}  $inputs
     * @return array{
     *     service_label: string,
     *     normal_daily_usd: float,
     *     integral_daily_usd: float,
     *     lines: list<array{concept: string, type: EmployeeSettlementLineType, label: string, formula: string, amount_usd: float}>
     * }
     */
    public function calculate(
        CarbonImmutable $hiredAt,
        CarbonImmutable $lastWorkDay,
        float $monthlySalaryUsd,
        EmployeeExitReason $reason,
        array $parameters,
        array $inputs,
    ): array {
        $service = $hiredAt->diff($lastWorkDay->addDay());
        $years = $service->y;

        $vacationDays = min($parameters['vacation_base_days'] + $years, $parameters['vacation_max_days']);
        $bonusDays = min($parameters['vacation_bonus_base_days'] + $years, $parameters['vacation_bonus_max_days']);

        $normalDaily = round($monthlySalaryUsd / 30, 4);
        $utilitiesShare = $normalDaily * $parameters['utilities_days_per_year'] / 360;
        $bonusShare = $normalDaily * $bonusDays / 360;
        $integralDaily = round($normalDaily + $utilitiesShare + $bonusShare, 4);

        $retroactiveDays = $this->retroactiveDays($service->y, $service->m, $service->d);
        $retroactive = round($retroactiveDays * $integralDaily, 2);
        $guarantee = round($inputs['guarantee_accrued_usd'], 2);
        $severance = max($retroactive, $guarantee);

        $lines = [];
        $add = function (string $concept, string $label, string $formula, float $amount, EmployeeSettlementLineType $type = EmployeeSettlementLineType::Assignment) use (&$lines): void {
            if ($amount > 0) {
                $lines[] = compact('concept', 'type', 'label', 'formula') + ['amount_usd' => round($amount, 2)];
            }
        };

        $add(
            'severance',
            'Prestaciones sociales',
            "Mayor entre garantía acumulada (US$ {$this->money($guarantee)}) y retroactivo ({$retroactiveDays} días × US$ {$integralDaily} = US$ {$this->money($retroactive)})",
            $severance,
        );
        $add('interest', 'Intereses sobre prestaciones', 'Monto cargado desde contabilidad', $inputs['accrued_interest_usd']);

        if ($reason === EmployeeExitReason::UnjustifiedDismissal) {
            $add('indemnity', 'Indemnización por despido injustificado', 'Monto igual a las prestaciones (art. 92)', $severance);
        }

        $add(
            'vacation_pending',
            'Vacaciones vencidas',
            "{$this->number($inputs['pending_vacation_days'])} días × US$ {$normalDaily}",
            $inputs['pending_vacation_days'] * $normalDaily,
        );
        $add(
            'bonus_pending',
            'Bono vacacional vencido',
            "{$this->number($inputs['pending_bonus_days'])} días × US$ {$normalDaily}",
            $inputs['pending_bonus_days'] * $normalDaily,
        );

        $serviceMonths = $service->m;
        $add(
            'vacation_fraction',
            'Vacaciones fraccionadas',
            "{$vacationDays} días ÷ 12 × {$serviceMonths} meses × US$ {$normalDaily}",
            $vacationDays / 12 * $serviceMonths * $normalDaily,
        );
        $add(
            'bonus_fraction',
            'Bono vacacional fraccionado',
            "{$bonusDays} días ÷ 12 × {$serviceMonths} meses × US$ {$normalDaily}",
            $bonusDays / 12 * $serviceMonths * $normalDaily,
        );

        $utilityMonths = $this->utilityMonths($hiredAt, $lastWorkDay);
        $add(
            'utilities_fraction',
            'Utilidades fraccionadas',
            "{$parameters['utilities_days_per_year']} días ÷ 12 × {$utilityMonths} meses × US$ {$normalDaily}",
            $parameters['utilities_days_per_year'] / 12 * $utilityMonths * $normalDaily,
        );
        $add(
            'unpaid_salary',
            'Salario pendiente',
            "{$this->number($inputs['unpaid_salary_days'])} días × US$ {$normalDaily}",
            $inputs['unpaid_salary_days'] * $normalDaily,
        );

        $add('advances', 'Anticipos de prestaciones', 'Monto cargado desde contabilidad', $inputs['advances_usd'], EmployeeSettlementLineType::Deduction);
        $add('utilities_paid', 'Utilidades adelantadas del año', 'Monto cargado por RR. HH.', $inputs['utilities_paid_usd'], EmployeeSettlementLineType::Deduction);
        $add('loans', 'Préstamos pendientes', 'Saldo de los préstamos activos', $inputs['loans_usd'], EmployeeSettlementLineType::Deduction);

        return [
            'service_label' => "{$service->y} años, {$service->m} meses y {$service->d} días",
            'normal_daily_usd' => $normalDaily,
            'integral_daily_usd' => $integralDaily,
            'lines' => $lines,
        ];
    }

    private function retroactiveDays(int $years, int $months, int $days): int
    {
        if ($years === 0 && $months < 3) {
            return 5 * ($months + ($days > 0 ? 1 : 0));
        }

        $hasQualifyingFraction = $months > 6 || ($months === 6 && $days > 0);

        return 30 * ($years + ($hasQualifyingFraction ? 1 : 0));
    }

    /**
     * Meses completos del ejercicio en curso, contados desde el 1 de enero o desde el ingreso.
     */
    private function utilityMonths(CarbonImmutable $hiredAt, CarbonImmutable $lastWorkDay): int
    {
        $yearStart = $lastWorkDay->startOfYear();
        $start = $hiredAt->greaterThan($yearStart) ? $hiredAt : $yearStart;
        $elapsed = $start->diff($lastWorkDay->addDay());

        return $elapsed->y * 12 + $elapsed->m;
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }

    private function number(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, ',', '.'), '0'), ',');
    }
}
