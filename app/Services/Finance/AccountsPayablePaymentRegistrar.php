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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra un pago a cuenta por pagar: actualiza principal/saldo y escribe el histórico de compras.
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
     *     amount_paid_ves: float|string,
     *     notes?: string|null,
     * }  $data
     */
    public function register(AccountsPayable $accountsPayable, array $data, ?User $actor = null): PurchaseHistory
    {
        $actor ??= Auth::user();
        $actorLabel = $actor instanceof User
            ? (string) ($actor->email ?? $actor->name ?? 'usuario_'.$actor->getKey())
            : 'sistema';

        return DB::transaction(function () use ($accountsPayable, $data, $actorLabel): PurchaseHistory {
            /** @var AccountsPayable $ap */
            $ap = AccountsPayable::query()->whereKey($accountsPayable->getKey())->lockForUpdate()->firstOrFail();

            if ($ap->status !== AccountsPayableStatus::POR_PAGAR) {
                throw ValidationException::withMessages([
                    'amount_paid_ves' => 'Esta cuenta por pagar ya no admite pagos (estado distinto de «Por pagar»).',
                ]);
            }

            $purchase = Purchase::query()->whereKey($ap->purchase_id)->firstOrFail();

            $paidAt = Carbon::parse($data['paid_at']);

            $amountPaidVes = round((float) $data['amount_paid_ves'], 2);
            if ($amountPaidVes <= 0) {
                throw ValidationException::withMessages([
                    'amount_paid_ves' => 'Indique un monto pagado mayor a cero.',
                ]);
            }

            $ratePayment = $this->snapshotBuilder->resolveRateForPurchase($purchase, $paidAt);
            if ($ratePayment === null || $ratePayment <= 0) {
                throw ValidationException::withMessages([
                    'paid_at' => 'No se obtuvo tasa BCV oficial para la fecha/hora del pago; corrija la fecha o intente más tarde.',
                ]);
            }

            $paidUsd = round($amountPaidVes / $ratePayment, 2);
            $remainingUsd = (float) ($ap->remaining_principal_usd ?? $ap->purchase_total_usd);

            if ($paidUsd > $remainingUsd + 0.01) {
                throw ValidationException::withMessages([
                    'amount_paid_ves' => 'El monto equivalente en USD ('.number_format($paidUsd, 2, ',', '.').') supera el principal pendiente ('.number_format($remainingUsd, 2, ',', '.').' USD).',
                ]);
            }

            $newRemainingUsd = max(0, round($remainingUsd - $paidUsd, 2));

            $rateToday = $this->rateClient->rateForDate(now());
            $newCurrentBalanceVes = ($rateToday !== null && $rateToday > 0)
                ? round($newRemainingUsd * $rateToday, 2)
                : max(0, round((float) $ap->current_balance_ves - $amountPaidVes, 2));

            $snapshot = $this->snapshotBuilder->build($purchase);

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
                'bcv_rate_at_payment' => round($ratePayment, 2),
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'created_by' => $actorLabel,
            ]);

            $ap->remaining_principal_usd = $newRemainingUsd;
            $ap->current_balance_ves = (string) $newCurrentBalanceVes;
            $ap->status = $newRemainingUsd < 0.01 ? AccountsPayableStatus::PAGADO : AccountsPayableStatus::POR_PAGAR;
            $ap->last_balance_recalculated_at = now();
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
                    'bcv_rate_at_payment' => $ratePayment,
                    'remaining_principal_usd_after' => $newRemainingUsd,
                    'current_balance_ves_after' => $newCurrentBalanceVes,
                ],
            );

            return $history;
        });
    }
}
