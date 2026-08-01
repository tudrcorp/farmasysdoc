<?php

namespace App\Services\Finance;

use App\Models\AccountsPayable;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\AccountsPayableStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recalcula el saldo al día (Bs) de una CxP con la tasa BCV oficial del día.
 */
final class AccountsPayableCurrentBalanceRecalculator
{
    public function __construct(
        private readonly VenezuelaOfficialUsdVesRateClient $rateClient,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     rate: float|null,
     *     processed: int,
     *     changed: int,
     *     error: string|null,
     * }
     */
    public function recalculateMany(Builder $query): array
    {
        $rateToday = $this->rateClient->rateForDate(now());

        if ($rateToday === null || $rateToday <= 0) {
            AuditLogger::record(
                event: 'accounts_payable_manual_bulk_recalc_rate_unavailable',
                description: 'CxP: sincronización masiva omitida por no disponer de tasa BCV oficial para la fecha en curso.',
                properties: [
                    'target_date' => now()->toDateString(),
                ],
            );

            return [
                'ok' => false,
                'rate' => null,
                'processed' => 0,
                'changed' => 0,
                'error' => 'No hay tasa BCV disponible para hoy. Intente más tarde.',
            ];
        }

        $processed = 0;
        $changed = 0;

        (clone $query)
            ->where('status', AccountsPayableStatus::POR_PAGAR)
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$processed, &$changed): void {
                foreach ($chunk as $accountsPayable) {
                    if (! $accountsPayable instanceof AccountsPayable) {
                        continue;
                    }

                    $result = $this->recalculate($accountsPayable, audit: false);

                    if (! $result['ok']) {
                        continue;
                    }

                    if (abs((float) $result['previous_balance_ves'] - (float) $result['new_balance_ves']) >= 0.005) {
                        $changed++;
                    }

                    $processed++;
                }
            });

        AuditLogger::record(
            event: 'accounts_payable_manual_bulk_recalc_completed',
            description: 'CxP: sincronización masiva de saldos al día con tasa BCV del día.',
            properties: [
                'records_processed' => $processed,
                'records_with_balance_change' => $changed,
                'bcv_rate_applied' => $rateToday,
                'as_of' => now()->toIso8601String(),
            ],
        );

        return [
            'ok' => true,
            'rate' => $rateToday,
            'processed' => $processed,
            'changed' => $changed,
            'error' => null,
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     rate: float|null,
     *     principal_usd: float|null,
     *     previous_balance_ves: float|null,
     *     new_balance_ves: float|null,
     *     error: string|null,
     * }
     */
    public function recalculate(AccountsPayable $accountsPayable, bool $audit = true): array
    {
        if ($accountsPayable->status !== AccountsPayableStatus::POR_PAGAR) {
            return $this->failure('Solo se pueden sincronizar cuentas en estado «Por pagar».');
        }

        $rateToday = $this->rateClient->rateForDate(now());

        if ($rateToday === null || $rateToday <= 0) {
            if ($audit) {
                AuditLogger::record(
                    event: 'accounts_payable_manual_recalc_rate_unavailable',
                    description: 'CxP: sincronización manual omitida por no disponer de tasa BCV oficial para la fecha en curso.',
                    auditableType: AccountsPayable::class,
                    auditableId: (string) $accountsPayable->getKey(),
                    auditableLabel: $accountsPayable->supplier_invoice_number,
                    properties: [
                        'target_date' => now()->toDateString(),
                    ],
                );
            }

            return $this->failure('No hay tasa BCV disponible para hoy. Intente más tarde.');
        }

        $principalUsd = round((float) ($accountsPayable->remaining_principal_usd ?? $accountsPayable->purchase_total_usd), 2);
        $previousBalance = round((float) $accountsPayable->current_balance_ves, 2);
        $newBalance = round($principalUsd * $rateToday, 2);

        $accountsPayable->current_balance_ves = (string) $newBalance;
        $accountsPayable->last_balance_recalculated_at = now();
        $accountsPayable->saveQuietly();

        if ($audit) {
            AuditLogger::record(
                event: 'accounts_payable_manual_recalc_completed',
                description: 'CxP: se sincronizó el saldo al día con la tasa BCV del día.',
                auditableType: AccountsPayable::class,
                auditableId: (string) $accountsPayable->getKey(),
                auditableLabel: $accountsPayable->supplier_invoice_number,
                properties: [
                    'bcv_rate_applied' => $rateToday,
                    'principal_usd' => $principalUsd,
                    'previous_balance_ves' => $previousBalance,
                    'new_balance_ves' => $newBalance,
                    'as_of' => now()->toIso8601String(),
                ],
            );
        }

        return [
            'ok' => true,
            'rate' => $rateToday,
            'principal_usd' => $principalUsd,
            'previous_balance_ves' => $previousBalance,
            'new_balance_ves' => $newBalance,
            'error' => null,
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     rate: float|null,
     *     principal_usd: float|null,
     *     previous_balance_ves: float|null,
     *     new_balance_ves: float|null,
     *     error: string|null,
     * }
     */
    private function failure(string $error): array
    {
        return [
            'ok' => false,
            'rate' => null,
            'principal_usd' => null,
            'previous_balance_ves' => null,
            'new_balance_ves' => null,
            'error' => $error,
        ];
    }
}
