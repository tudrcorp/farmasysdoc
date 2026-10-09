<?php

namespace App\Services\Finance;

use App\Models\AccountsPayable;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\AccountsPayableInvoiceTaxSnapshot;
use App\Support\Finance\AccountsPayableStatus;
use App\Support\Finance\BcvRate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula solo el total a pagar (Bs) de una CxP.
 *
 * Cada paso se redondea a 2 decimales (half up):
 * USD = redondear(total a pagar a tasa de carga ÷ tasa BCV del registro);
 * saldo nuevo = redondear(USD × tasa BCV del día, también a 2 decimales).
 * La tasa del registro conserva los decimales con los que se cargó la compra.
 */
final class AccountsPayableCurrentBalanceRecalculator
{
    public function __construct(
        private readonly VenezuelaOfficialUsdVesRateClient $rateClient,
    ) {}

    public static function roundMoney(float $amount): float
    {
        return round($amount, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * Indexa el total a pagar a la tasa BCV del día. Null si la tasa no sirve.
     */
    public static function indexedBalanceVes(
        float $amountPayableVes,
        float $registrationRate,
        float $rateToday,
        float $remainingRatio = 1.0,
    ): ?float {
        $rateToday = BcvRate::truncate($rateToday);
        $amountPayableVes = self::roundMoney($amountPayableVes);

        if ($rateToday <= 0 || $registrationRate <= 0 || $amountPayableVes < 0) {
            return null;
        }

        $ratio = max(0.0, min(1.0, $remainingRatio));
        $payableUsd = self::roundMoney(self::roundMoney($amountPayableVes / $registrationRate) * $ratio);

        return self::roundMoney($payableUsd * $rateToday);
    }

    /**
     * @return array{
     *     ok: bool,
     *     rate: float|null,
     *     processed: int,
     *     changed: int,
     *     failed: int,
     *     lines: list<array{
     *         ok: bool,
     *         supplier: string,
     *         invoice: string,
     *         previous_balance_ves: float|null,
     *         new_balance_ves: float|null,
     *         payable_usd: float|null,
     *         error: string|null,
     *     }>,
     *     error: string|null,
     * }
     */
    public function recalculateMany(Builder $query, ?float $rateOverride = null, bool $audit = true, bool $withLines = true): array
    {
        $rateToday = $this->resolveTodayRate($rateOverride);

        if ($rateToday === null) {
            if ($audit) {
                AuditLogger::record(
                    event: 'accounts_payable_manual_bulk_recalc_rate_unavailable',
                    description: 'CxP: sincronización masiva omitida por no disponer de tasa BCV oficial para la fecha en curso.',
                    properties: [
                        'target_date' => now()->toDateString(),
                    ],
                );
            }

            return $this->manyFailure('No hay tasa BCV disponible para hoy. Intente más tarde.');
        }

        $processed = 0;
        $changed = 0;
        $failed = 0;
        $lines = [];

        (clone $query)
            ->with(['purchase.purchaseBook', 'purchase.supplier'])
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$processed, &$changed, &$failed, &$lines, $rateToday, $withLines): void {
                /** @var list<AccountsPayable> $payable */
                $payable = [];

                foreach ($chunk as $accountsPayable) {
                    if (! $accountsPayable instanceof AccountsPayable) {
                        continue;
                    }

                    if ($accountsPayable->status !== AccountsPayableStatus::POR_PAGAR) {
                        $failed++;
                        if ($withLines) {
                            $lines[] = $this->line(
                                $accountsPayable,
                                ok: false,
                                error: 'Solo se sincronizan cuentas en estado «Por pagar».',
                            );
                        }

                        continue;
                    }

                    $payable[] = $accountsPayable;
                }

                if ($payable === []) {
                    return;
                }

                $committed = DB::transaction(function () use ($payable, $rateToday, $withLines): array {
                    $chunkProcessed = 0;
                    $chunkChanged = 0;
                    $chunkFailed = 0;
                    $chunkLines = [];
                    $syncedAt = now();

                    foreach ($payable as $accountsPayable) {
                        $locked = AccountsPayable::query()
                            ->whereKey($accountsPayable->getKey())
                            ->lockForUpdate()
                            ->first();

                        if (! $locked instanceof AccountsPayable || $locked->status !== AccountsPayableStatus::POR_PAGAR) {
                            $chunkFailed++;
                            if ($withLines) {
                                $chunkLines[] = $this->line(
                                    $accountsPayable,
                                    ok: false,
                                    error: 'La cuenta cambió de estado durante la sincronización y no se modificó.',
                                );
                            }

                            continue;
                        }

                        $locked->setRelations($accountsPayable->getRelations());
                        $previousDisplayed = self::roundMoney(
                            AccountsPayableInvoiceTaxSnapshot::listedAmountPayableVes($locked),
                        );
                        $computed = $this->compute($locked, $rateToday);

                        if (! $computed['ok']) {
                            $chunkFailed++;
                            if ($withLines) {
                                $chunkLines[] = $this->line(
                                    $locked,
                                    ok: false,
                                    error: (string) $computed['error'],
                                );
                            }

                            continue;
                        }

                        $newBalance = (float) $computed['new_balance_ves'];
                        $locked->current_balance_ves = number_format($newBalance, 2, '.', '');
                        $locked->last_balance_recalculated_at = $syncedAt;
                        $locked->saveQuietly();

                        $chunkProcessed++;
                        if (abs($previousDisplayed - $newBalance) >= 0.005) {
                            $chunkChanged++;
                        }

                        if ($withLines) {
                            $chunkLines[] = $this->line(
                                $locked,
                                ok: true,
                                previous: $previousDisplayed,
                                newBalance: $newBalance,
                                payableUsd: (float) $computed['payable_usd'],
                            );
                        }
                    }

                    return [
                        'processed' => $chunkProcessed,
                        'changed' => $chunkChanged,
                        'failed' => $chunkFailed,
                        'lines' => $chunkLines,
                    ];
                });

                $processed += $committed['processed'];
                $changed += $committed['changed'];
                $failed += $committed['failed'];

                if ($withLines) {
                    array_push($lines, ...$committed['lines']);
                }
            });

        if ($audit) {
            AuditLogger::record(
                event: 'accounts_payable_manual_bulk_recalc_completed',
                description: 'CxP: sincronización del total a pagar con tasa BCV del día (2 decimales, redondeo).',
                properties: [
                    'records_processed' => $processed,
                    'records_with_balance_change' => $changed,
                    'records_failed' => $failed,
                    'bcv_rate_applied' => $rateToday,
                    'as_of' => now()->toIso8601String(),
                ],
            );
        }

        return [
            'ok' => true,
            'rate' => $rateToday,
            'processed' => $processed,
            'changed' => $changed,
            'failed' => $failed,
            'lines' => $lines,
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
    public function recalculate(AccountsPayable $accountsPayable, bool $audit = true, ?float $rateOverride = null): array
    {
        if ($accountsPayable->status !== AccountsPayableStatus::POR_PAGAR) {
            return $this->failure('Solo se pueden sincronizar cuentas en estado «Por pagar».');
        }

        $rateToday = $this->resolveTodayRate($rateOverride);

        if ($rateToday === null) {
            $computed = $this->failure('No hay tasa BCV disponible para hoy. Intente más tarde.');
        } else {
            $computed = DB::transaction(function () use ($accountsPayable, $rateToday): array {
                $locked = AccountsPayable::query()
                    ->whereKey($accountsPayable->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $locked instanceof AccountsPayable || $locked->status !== AccountsPayableStatus::POR_PAGAR) {
                    return $this->failure('La cuenta cambió de estado durante la sincronización y no se modificó.');
                }

                $accountsPayable->loadMissing(['purchase.purchaseBook', 'purchase.supplier']);
                $locked->setRelations($accountsPayable->getRelations());

                $computed = $this->compute($locked, $rateToday);

                if (! $computed['ok']) {
                    return $computed;
                }

                $previousBalance = self::roundMoney(AccountsPayableInvoiceTaxSnapshot::listedAmountPayableVes($locked));
                $newBalance = (float) $computed['new_balance_ves'];

                $locked->current_balance_ves = number_format($newBalance, 2, '.', '');
                $locked->last_balance_recalculated_at = now();
                $locked->saveQuietly();

                $accountsPayable->current_balance_ves = $locked->current_balance_ves;
                $accountsPayable->last_balance_recalculated_at = $locked->last_balance_recalculated_at;

                return [
                    ...$computed,
                    'previous_balance_ves' => $previousBalance,
                ];
            });
        }

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

        if ($audit) {
            AuditLogger::record(
                event: 'accounts_payable_manual_recalc_completed',
                description: 'CxP: se sincronizó el total a pagar (USD a 2 decimales × tasa BCV del día a 2 decimales).',
                auditableType: AccountsPayable::class,
                auditableId: (string) $accountsPayable->getKey(),
                auditableLabel: $accountsPayable->supplier_invoice_number,
                properties: [
                    'bcv_rate_applied' => $computed['rate'],
                    'bcv_rate_at_registration' => $computed['registration_rate'],
                    'amount_payable_ves' => $computed['amount_payable_ves'],
                    'payable_usd' => $computed['payable_usd'],
                    'principal_usd' => $computed['principal_usd'],
                    'previous_balance_ves' => $computed['previous_balance_ves'],
                    'new_balance_ves' => $computed['new_balance_ves'],
                    'as_of' => now()->toIso8601String(),
                ],
            );
        }

        return $computed;
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
    public function compute(AccountsPayable $accountsPayable, ?float $rateOverride = null): array
    {
        $rateToday = $this->resolveTodayRate($rateOverride);

        if ($rateToday === null) {
            return $this->failure('No hay tasa BCV disponible para hoy. Intente más tarde.');
        }

        $accountsPayable->loadMissing(['purchase.purchaseBook', 'purchase.supplier']);

        $registrationRate = AccountsPayableInvoiceTaxSnapshot::purchaseRegistrationBcvRate($accountsPayable);

        if ($registrationRate === null || $registrationRate <= 0) {
            return $this->failure('No se pudo determinar la tasa BCV del registro de la compra.');
        }

        $amountPayableVes = AccountsPayableInvoiceTaxSnapshot::amountPayableVes($accountsPayable);

        if ($amountPayableVes < 0) {
            return $this->failure('La retención supera el total de la factura.');
        }

        $purchaseTotalUsd = self::roundMoney((float) $accountsPayable->purchase_total_usd);
        $remainingPrincipalUsd = self::roundMoney((float) ($accountsPayable->remaining_principal_usd ?? $purchaseTotalUsd));
        $ratio = $purchaseTotalUsd > 0
            ? max(0.0, min(1.0, $remainingPrincipalUsd / $purchaseTotalUsd))
            : 1.0;

        $payableUsd = self::roundMoney(self::roundMoney($amountPayableVes / $registrationRate) * $ratio);
        $newBalance = self::indexedBalanceVes($amountPayableVes, $registrationRate, $rateToday, $ratio);

        if ($newBalance === null) {
            return $this->failure('No se pudo calcular el total a pagar con la tasa BCV.');
        }

        return [
            'ok' => true,
            'rate' => $rateToday,
            'registration_rate' => $registrationRate,
            'amount_payable_ves' => $amountPayableVes,
            'payable_usd' => $payableUsd,
            'principal_usd' => $remainingPrincipalUsd,
            'previous_balance_ves' => null,
            'new_balance_ves' => $newBalance,
            'error' => null,
        ];
    }

    private function resolveTodayRate(?float $rateOverride): ?float
    {
        if ($rateOverride !== null && $rateOverride > 0) {
            $rate = BcvRate::truncate($rateOverride);
        } else {
            $fetched = $this->rateClient->rateForDate(now());

            if ($fetched === null || $fetched <= 0) {
                return null;
            }

            $rate = BcvRate::truncate($fetched);
        }

        return $rate > 0 ? $rate : null;
    }

    /**
     * @return array{
     *     ok: bool,
     *     supplier: string,
     *     invoice: string,
     *     previous_balance_ves: float|null,
     *     new_balance_ves: float|null,
     *     payable_usd: float|null,
     *     error: string|null,
     * }
     */
    private function line(
        AccountsPayable $accountsPayable,
        bool $ok,
        ?float $previous = null,
        ?float $newBalance = null,
        ?float $payableUsd = null,
        ?string $error = null,
    ): array {
        return [
            'ok' => $ok,
            'supplier' => (string) $accountsPayable->supplier_name,
            'invoice' => (string) $accountsPayable->supplier_invoice_number,
            'previous_balance_ves' => $previous,
            'new_balance_ves' => $newBalance,
            'payable_usd' => $payableUsd,
            'error' => $error,
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

    /**
     * @return array{
     *     ok: bool,
     *     rate: float|null,
     *     processed: int,
     *     changed: int,
     *     failed: int,
     *     lines: list<array{
     *         ok: bool,
     *         supplier: string,
     *         invoice: string,
     *         previous_balance_ves: float|null,
     *         new_balance_ves: float|null,
     *         payable_usd: float|null,
     *         error: string|null,
     *     }>,
     *     error: string|null,
     * }
     */
    private function manyFailure(string $error): array
    {
        return [
            'ok' => false,
            'rate' => null,
            'processed' => 0,
            'changed' => 0,
            'failed' => 0,
            'lines' => [],
            'error' => $error,
        ];
    }
}
