<?php

namespace App\Support\Cash;

use App\Enums\VenezuelanPagoMovilBank;

final class PhysicalCashBoxPosBankReconciler
{
    public const UnassignedBankCode = '__unassigned__';

    /**
     * @param  list<array{bank_code?: mixed, amount_ves?: mixed}>  $declaredLines
     * @param  list<array{id?: int|null, label?: string, amount_ves?: float, bank_code?: string|null}>  $systemTerminals
     * @return array{
     *     lines: list<array{
     *         bank_code: string,
     *         bank_label: string,
     *         declared_ves: float,
     *         system_ves: float,
     *         difference_ves: float,
     *         status: string,
     *         status_label: string,
     *     }>,
     *     declared_total_ves: float,
     *     system_total_ves: float,
     *     difference_ves: float,
     *     has_mismatch: bool,
     * }
     */
    public function reconcile(array $declaredLines, array $systemTerminals): array
    {
        $declaredByBank = [];
        foreach ($declaredLines as $line) {
            $bankCode = trim((string) ($line['bank_code'] ?? ''));
            if ($bankCode === '') {
                continue;
            }

            $declaredByBank[$bankCode] = round(
                ($declaredByBank[$bankCode] ?? 0.0) + max(0.0, (float) ($line['amount_ves'] ?? 0)),
                2,
            );
        }

        $systemByBank = [];
        $unassignedVes = 0.0;
        foreach ($systemTerminals as $terminal) {
            $amount = round((float) ($terminal['amount_ves'] ?? 0), 2);
            $bankCode = trim((string) ($terminal['bank_code'] ?? ''));
            if ($bankCode === '') {
                $unassignedVes = round($unassignedVes + $amount, 2);

                continue;
            }

            $systemByBank[$bankCode] = round(($systemByBank[$bankCode] ?? 0.0) + $amount, 2);
        }

        $bankCodes = array_values(array_unique([
            ...array_keys($declaredByBank),
            ...array_keys($systemByBank),
        ]));
        sort($bankCodes);

        $lines = [];
        foreach ($bankCodes as $bankCode) {
            $declared = round((float) ($declaredByBank[$bankCode] ?? 0), 2);
            $system = round((float) ($systemByBank[$bankCode] ?? 0), 2);
            if ($declared < 0.01 && $system < 0.01) {
                continue;
            }

            $lines[] = $this->makeLine($bankCode, $this->bankLabel($bankCode), $declared, $system);
        }

        if ($unassignedVes >= 0.01) {
            $lines[] = $this->makeLine(
                self::UnassignedBankCode,
                'POS sin punto asignado',
                0.0,
                $unassignedVes,
            );
        }

        $declaredTotal = round((float) collect($lines)->sum('declared_ves'), 2);
        $systemTotal = round((float) collect($lines)->sum('system_ves'), 2);
        $difference = round($declaredTotal - $systemTotal, 2);

        return [
            'lines' => $lines,
            'declared_total_ves' => $declaredTotal,
            'system_total_ves' => $systemTotal,
            'difference_ves' => $difference,
            'has_mismatch' => PhysicalCashBoxCloseVariance::isMismatch($difference)
                || collect($lines)->contains(
                    fn (array $line): bool => PhysicalCashBoxCloseVariance::isMismatch((float) $line['difference_ves']),
                ),
        ];
    }

    /**
     * @return array{
     *     bank_code: string,
     *     bank_label: string,
     *     declared_ves: float,
     *     system_ves: float,
     *     difference_ves: float,
     *     status: string,
     *     status_label: string,
     * }
     */
    private function makeLine(string $bankCode, string $bankLabel, float $declared, float $system): array
    {
        $difference = round($declared - $system, 2);

        return [
            'bank_code' => $bankCode,
            'bank_label' => $bankLabel,
            'declared_ves' => $declared,
            'system_ves' => $system,
            'difference_ves' => $difference,
            'status' => PhysicalCashBoxCloseVariance::status($difference),
            'status_label' => PhysicalCashBoxCloseVariance::statusLabel($difference),
        ];
    }

    private function bankLabel(string $bankCode): string
    {
        return VenezuelanPagoMovilBank::tryFrom($bankCode)?->optionLabel() ?? $bankCode;
    }
}
