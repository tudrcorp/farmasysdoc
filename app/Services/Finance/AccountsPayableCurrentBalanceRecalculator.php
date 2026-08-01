<?php

namespace App\Services\Finance;

use App\Models\AccountsPayable;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\AccountsPayableInvoiceTaxSnapshot;
use App\Support\Finance\AccountsPayableStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recalcula el saldo al día (Bs) de una CxP:
 * total a pagar (Bs) ÷ tasa BCV del registro de la compra × tasa BCV del día de sincronización.
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
            ->with(['purchase.purchaseBook', 'purchase.supplier'])
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
     *     registration_rate: float|null,
     *     amount_payable_ves: float|null,
     *     payable_usd: float|null,
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

        $computed = $this->compute($accountsPayable);

        if (! $computed['ok']) {
            if ($audit) {
                AuditLogger::record(
                    event: 'accounts_payable_manual_recalc_failed',
                    description: 'CxP: sincronización manual omitida: '.$computed['error'],
                    auditableType: AccountsPayable::class,
                    auditableId: (string) $accountsPayable->getKey(),
                    auditableLabel: $accountsPayable->supplier_invoice_number,
                    properties: [
                        'target_date' => now()->toDateString(),
                        'error' => $computed['error'],
                    ],
                );
            }

            return $computed;
        }

        $previousBalance = round((float) $accountsPayable->current_balance_ves, 2);
        $newBalance = (float) $computed['new_balance_ves'];

        $accountsPayable->current_balance_ves = (string) $newBalance;
        $accountsPayable->last_balance_recalculated_at = now();
        $accountsPayable->saveQuietly();

        if ($audit) {
            AuditLogger::record(
                event: 'accounts_payable_manual_recalc_completed',
                description: 'CxP: se sincronizó el saldo al día (total a pagar ÷ tasa registro × tasa BCV del día).',
                auditableType: AccountsPayable::class,
                auditableId: (string) $accountsPayable->getKey(),
                auditableLabel: $accountsPayable->supplier_invoice_number,
                properties: [
                    'bcv_rate_applied' => $computed['rate'],
                    'bcv_rate_at_registration' => $computed['registration_rate'],
                    'amount_payable_ves' => $computed['amount_payable_ves'],
                    'payable_usd' => $computed['payable_usd'],
                    'principal_usd' => $computed['principal_usd'],
                    'previous_balance_ves' => $previousBalance,
                    'new_balance_ves' => $newBalance,
                    'as_of' => now()->toIso8601String(),
                ],
            );
        }

        return [
            ...$computed,
            'previous_balance_ves' => $previousBalance,
        ];
    }

    /**
     * Calcula el saldo al día sin persistir.
     *
     * @return array{
     *     ok: bool,
     *     rate: float|null,
     *     registration_rate: float|null,
     *     amount_payable_ves: float|null,
     *     payable_usd: float|null,
     *     principal_usd: float|null,
     *     previous_balance_ves: float|null,
     *     new_balance_ves: float|null,
     *     error: string|null,
     * }
     */
    public function compute(AccountsPayable $accountsPayable): array
    {
        $rateToday = $this->rateClient->rateForDate(now());

        if ($rateToday === null || $rateToday <= 0) {
            return $this->failure('No hay tasa BCV disponible para hoy. Intente más tarde.');
        }

        $accountsPayable->loadMissing(['purchase.purchaseBook', 'purchase.supplier']);

        $registrationRate = AccountsPayableInvoiceTaxSnapshot::purchaseRegistrationBcvRate($accountsPayable);

        if ($registrationRate === null || $registrationRate <= 0) {
            return $this->failure('No se pudo determinar la tasa BCV del registro de la compra.');
        }

        $amountPayableVes = AccountsPayableInvoiceTaxSnapshot::amountPayableVes($accountsPayable);
        $payableUsdFull = round($amountPayableVes / $registrationRate, 8);

        $purchaseTotalUsd = (float) $accountsPayable->purchase_total_usd;
        $remainingPrincipalUsd = (float) ($accountsPayable->remaining_principal_usd ?? $purchaseTotalUsd);
        $ratio = $purchaseTotalUsd > 0.00001
            ? max(0.0, min(1.0, $remainingPrincipalUsd / $purchaseTotalUsd))
            : 1.0;

        $payableUsd = round($payableUsdFull * $ratio, 8);
        $newBalance = round($payableUsd * $rateToday, 2);

        return [
            'ok' => true,
            'rate' => $rateToday,
            'registration_rate' => $registrationRate,
            'amount_payable_ves' => $amountPayableVes,
            'payable_usd' => round($payableUsd, 2),
            'principal_usd' => round($remainingPrincipalUsd, 2),
            'previous_balance_ves' => null,
            'new_balance_ves' => $newBalance,
            'error' => null,
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     rate: float|null,
     *     registration_rate: float|null,
     *     amount_payable_ves: float|null,
     *     payable_usd: float|null,
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
            'registration_rate' => null,
            'amount_payable_ves' => null,
            'payable_usd' => null,
            'principal_usd' => null,
            'previous_balance_ves' => null,
            'new_balance_ves' => null,
            'error' => $error,
        ];
    }
}
