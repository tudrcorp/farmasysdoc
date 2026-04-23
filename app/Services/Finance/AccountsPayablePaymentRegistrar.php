<?php

namespace App\Services\Finance;

use App\Models\AccountsPayable;
use App\Models\Purchase;
use App\Models\PurchaseHistory;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\AccountsPayableStatus;
use App\Support\Purchases\PurchaseHistoryEntryType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra un pago a cuenta por pagar: valida USD/Bs con tasa BCV del día actual,
 * actualiza principal/saldo, marca pagado y escribe el histórico de compras.
 */
final class AccountsPayablePaymentRegistrar
{
    public function __construct(
        private readonly PurchaseMonetarySnapshotBuilder $snapshotBuilder,
        private readonly VenezuelaOfficialUsdVesRateClient $rateClient,
    ) {}

    /**
     * @param  array{
     *     payment_method: string,
     *     payment_form: string,
     *     paid_at: string|\DateTimeInterface,
     *     amount_paid_usd: float|string,
     *     amount_paid_ves: float|string,
     *     payment_reference?: string|null,
     *     notes?: string|null,
     * }  $data
     */
    public function register(AccountsPayable $accountsPayable, array $data, ?User $actor = null): PurchaseHistory
    {
        $actor ??= Auth::user();
        $actorLabel = $this->actorLabel($actor);

        return DB::transaction(function () use ($accountsPayable, $data, $actorLabel): PurchaseHistory {
            /** @var AccountsPayable $ap */
            $ap = AccountsPayable::query()->whereKey($accountsPayable->getKey())->lockForUpdate()->firstOrFail();

            return $this->applyAfterLock($ap, $data, $actorLabel);
        });
    }

    /**
     * Liquida en una sola transacción varias CxP (cada una por su principal pendiente completo).
     *
     * @param  Collection<int, AccountsPayable>  $accountsPayables
     * @param  array{
     *     payment_method: string,
     *     payment_form: string,
     *     paid_at: string|\DateTimeInterface,
     *     payment_reference?: string|null,
     *     notes?: string|null,
     * }  $sharedData
     * @return list<PurchaseHistory>
     */
    public function registerBulkFullSettlement(Collection $accountsPayables, array $sharedData, ?User $actor = null): array
    {
        $actor ??= Auth::user();
        $actorLabel = $this->actorLabel($actor);

        $ids = $accountsPayables->pluck('id')->filter()->unique()->values()->all();

        AuditLogger::record(
            event: 'accounts_payable_bulk_payment_started',
            description: 'CxP: inicio de pago masivo (transacción única).',
            properties: [
                'accounts_payable_ids' => $ids,
                'count' => count($ids),
                'payment_method' => $sharedData['payment_method'] ?? null,
                'payment_form' => $sharedData['payment_form'] ?? null,
                'paid_at' => isset($sharedData['paid_at']) ? (string) $sharedData['paid_at'] : null,
            ],
        );

        $rateFx = $this->bcvRateForCurrentDayOrFail();

        try {
            return DB::transaction(function () use ($accountsPayables, $sharedData, $actorLabel, $rateFx): array {
                $sorted = $accountsPayables->unique('id')->sortBy('id');
                $histories = [];

                foreach ($sorted as $row) {
                    if (! $row instanceof AccountsPayable) {
                        continue;
                    }

                    /** @var AccountsPayable $ap */
                    $ap = AccountsPayable::query()->whereKey($row->getKey())->lockForUpdate()->firstOrFail();

                    if ($ap->status !== AccountsPayableStatus::POR_PAGAR) {
                        throw ValidationException::withMessages([
                            'amount_paid_usd' => 'La cuenta #'.$ap->getKey().' ya no está «Por pagar»; actualice la tabla y vuelva a intentar.',
                        ]);
                    }

                    $remainingUsd = round((float) ($ap->remaining_principal_usd ?? $ap->purchase_total_usd), 2);
                    if ($remainingUsd <= 0) {
                        throw ValidationException::withMessages([
                            'amount_paid_usd' => 'La cuenta #'.$ap->getKey().' no tiene principal pendiente en USD.',
                        ]);
                    }

                    $amountPaidVes = round($remainingUsd * $rateFx, 2);

                    $payload = array_merge($sharedData, [
                        'amount_paid_usd' => $remainingUsd,
                        'amount_paid_ves' => $amountPaidVes,
                    ]);

                    $histories[] = $this->applyAfterLock($ap, $payload, $actorLabel);
                }

                AuditLogger::record(
                    event: 'accounts_payable_bulk_payment_committed',
                    description: 'CxP: pago masivo confirmado en base de datos.',
                    properties: [
                        'accounts_payable_ids' => array_map(
                            static fn (PurchaseHistory $h): int => (int) ($h->accounts_payable_id ?? 0),
                            $histories,
                        ),
                        'purchase_history_ids' => array_map(
                            static fn (PurchaseHistory $h): int => (int) $h->getKey(),
                            $histories,
                        ),
                        'bcv_rate_used' => $rateFx,
                    ],
                );

                return $histories;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            AuditLogger::record(
                event: 'accounts_payable_bulk_payment_rolled_back',
                description: 'CxP: el pago masivo falló y la transacción se revirtió.',
                properties: [
                    'accounts_payable_ids' => $ids,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ],
            );
            throw $e;
        }
    }

    /**
     * @param  array{
     *     payment_method: string,
     *     payment_form: string,
     *     paid_at: string|\DateTimeInterface,
     *     amount_paid_usd: float|string,
     *     amount_paid_ves: float|string,
     *     payment_reference?: string|null,
     *     notes?: string|null,
     * }  $data
     */
    private function applyAfterLock(AccountsPayable $ap, array $data, string $actorLabel): PurchaseHistory
    {
        if ($ap->status !== AccountsPayableStatus::POR_PAGAR) {
            throw ValidationException::withMessages([
                'amount_paid_usd' => 'Esta cuenta por pagar ya no admite pagos (estado distinto de «Por pagar»).',
            ]);
        }

        $purchase = Purchase::query()->whereKey($ap->purchase_id)->firstOrFail();

        $paidAt = Carbon::parse($data['paid_at']);

        $amountPaidUsd = round((float) $data['amount_paid_usd'], 2);
        $amountPaidVes = round((float) $data['amount_paid_ves'], 2);

        if ($amountPaidUsd <= 0 || $amountPaidVes <= 0) {
            throw ValidationException::withMessages([
                'amount_paid_usd' => 'Indique montos mayores a cero en USD y en bolívares.',
            ]);
        }

        $rateFx = $this->bcvRateForCurrentDayOrFail();
        $this->assertUsdVesCoherentWithBcv($amountPaidUsd, $amountPaidVes, $rateFx);

        $remainingUsd = round((float) ($ap->remaining_principal_usd ?? $ap->purchase_total_usd), 2);

        if ($amountPaidUsd > $remainingUsd + 0.01) {
            throw ValidationException::withMessages([
                'amount_paid_usd' => 'El monto en USD ('.number_format($amountPaidUsd, 2, ',', '.').') supera el principal pendiente ('.number_format($remainingUsd, 2, ',', '.').' USD).',
            ]);
        }

        $paidUsd = $amountPaidUsd;
        $newRemainingUsd = max(0, round($remainingUsd - $paidUsd, 2));

        $rateToday = $this->rateClient->rateForDate(now());
        $newCurrentBalanceVes = ($rateToday !== null && $rateToday > 0)
            ? round($newRemainingUsd * $rateToday, 2)
            : max(0, round((float) $ap->current_balance_ves - $amountPaidVes, 2));

        $snapshot = $this->snapshotBuilder->build($purchase);

        $paymentRef = $this->normalizePaymentReference($data['payment_reference'] ?? null);

        $history = PurchaseHistory::query()->create([
            'entry_type' => PurchaseHistoryEntryType::PAGO_CUENTA_POR_PAGAR,
            'purchase_id' => $purchase->id,
            'branch_id' => $ap->branch_id,
            'accounts_payable_id' => $ap->id,
            'issued_at' => $snapshot !== null
                ? $snapshot['issued_at']->toDateString()
                : Carbon::parse($ap->issued_at)->toDateString(),
            'registered_in_system_date' => $snapshot !== null
                ? $snapshot['registered_at']->toDateString()
                : Carbon::parse($purchase->registered_in_system_date ?? $ap->issued_at)->toDateString(),
            'supplier_invoice_number' => $ap->supplier_invoice_number,
            'supplier_control_number' => $ap->supplier_control_number,
            'supplier_tax_id' => $ap->supplier_tax_id,
            'supplier_name' => $ap->supplier_name,
            'purchase_total_usd' => (float) $ap->purchase_total_usd,
            'purchase_total_ves_at_issue' => (float) $ap->purchase_total_ves_at_issue,
            'total_ves_at_system_registration' => $snapshot !== null
                ? $snapshot['ves_at_registration']
                : (float) $ap->original_balance_ves,
            'payment_method' => (string) $data['payment_method'],
            'payment_form' => (string) $data['payment_form'],
            'paid_at' => $paidAt,
            'amount_paid_ves' => $amountPaidVes,
            'amount_paid_usd' => $paidUsd,
            'bcv_rate_at_payment' => $rateFx,
            'payment_reference' => $paymentRef,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
            'created_by' => $actorLabel,
        ]);

        $ap->remaining_principal_usd = $newRemainingUsd;
        $ap->current_balance_ves = (string) $newCurrentBalanceVes;
        $ap->status = $newRemainingUsd < 0.01 ? AccountsPayableStatus::PAGADO : AccountsPayableStatus::POR_PAGAR;
        $ap->last_balance_recalculated_at = now();

        if ($ap->status === AccountsPayableStatus::PAGADO) {
            $ap->paid_at = $paidAt;
            $ap->payment_reference = $paymentRef;
        }

        $ap->saveQuietly();

        AuditLogger::forModel(
            $history,
            'purchase_history_cpp_payment_registered',
            [
                'origen' => 'usuario_registro_pago_cxp',
                'accounts_payable_id' => $ap->getKey(),
                'purchase_id' => $purchase->getKey(),
                'purchase_number' => $purchase->purchase_number,
                'payment_method' => (string) $data['payment_method'],
                'payment_form' => (string) $data['payment_form'],
                'amount_paid_ves' => $amountPaidVes,
                'amount_paid_usd' => $paidUsd,
                'payment_reference' => $paymentRef,
                'bcv_rate_at_payment' => $rateFx,
            ],
        );

        AuditLogger::forModel(
            $ap,
            'accounts_payable_payment_applied',
            [
                'purchase_history_id' => $history->getKey(),
                'payment_method' => $data['payment_method'],
                'payment_form' => $data['payment_form'],
                'paid_at' => $paidAt->toIso8601String(),
                'amount_paid_ves' => $amountPaidVes,
                'amount_paid_usd' => $paidUsd,
                'payment_reference' => $paymentRef,
                'bcv_rate_at_payment' => $rateFx,
                'remaining_principal_usd_after' => $newRemainingUsd,
                'current_balance_ves_after' => $newCurrentBalanceVes,
                'status_after' => $ap->status,
            ],
        );

        return $history;
    }

    private function actorLabel(?User $actor): string
    {
        if ($actor instanceof User) {
            return (string) ($actor->email ?? $actor->name ?? 'usuario_'.$actor->getKey());
        }

        return 'sistema';
    }

    private function bcvRateForCurrentDayOrFail(): float
    {
        $rate = $this->rateClient->rateForDate(now()->startOfDay());
        if ($rate === null || $rate <= 0) {
            throw ValidationException::withMessages([
                'amount_paid_ves' => 'No se obtuvo la tasa oficial Bs/USD (promedio) para el día actual; no puede registrar el pago.',
            ]);
        }

        return round((float) $rate, 2);
    }

    private function assertUsdVesCoherentWithBcv(float $usd, float $ves, float $rateFx): void
    {
        $expectedVes = round($usd * $rateFx, 2);
        $tolerance = max(0.02, abs($expectedVes) * 0.002);

        if (abs($ves - $expectedVes) > $tolerance) {
            throw ValidationException::withMessages([
                'amount_paid_ves' => 'El monto en Bs no coincide con el USD indicado usando la tasa BCV del día actual ('.number_format($rateFx, 2, ',', '.').' Bs/USD). Esperado aprox.: '.number_format($expectedVes, 2, ',', '.').' Bs.',
            ]);
        }
    }

    private function normalizePaymentReference(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' ? null : mb_substr($s, 0, 255);
    }
}
