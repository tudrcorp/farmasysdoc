<?php

namespace App\Support\Finance;

use App\Models\AccountsPayable;
use App\Models\PurchaseHistory;
use App\Models\User;
use App\Support\Purchases\PurchaseHistoryEntryType;
use App\Support\Purchases\PurchaseHistoryPaymentForm;
use App\Support\Purchases\PurchaseHistoryPaymentMethod;
use Illuminate\Support\Collection;

/**
 * Arma el payload del reporte PDF detallado de un pago a cuenta por pagar.
 */
final class AccountsPayablePaymentReportBuilder
{
    /**
     * @return array{
     *     accounts_payable: AccountsPayable,
     *     branch_name: string,
     *     purchase_number: string|null,
     *     status_label: string,
     *     tax_snapshot: AccountsPayableInvoiceTaxSnapshot,
     *     amount_payable_ves: float,
     *     payments: Collection<int, array{
     *         paid_at: string,
     *         payment_method: string,
     *         payment_form: string,
     *         amount_paid_usd: float,
     *         amount_paid_ves: float,
     *         bcv_rate: float|null,
     *         payment_reference: string|null,
     *         notes: string|null,
     *         created_by: string|null,
     *         retention_amount_ves: float|null,
     *         retention_voucher_number: int|null,
     *     }>,
     *     total_paid_usd: float,
     *     total_paid_ves: float,
     *     has_payment_proof: bool,
     *     generated_at: string,
     *     generated_by: string,
     * }
     */
    public function build(AccountsPayable $accountsPayable, ?User $actor = null): array
    {
        $accountsPayable->loadMissing(['branch', 'purchase']);

        /** @var Collection<int, PurchaseHistory> $histories */
        $histories = PurchaseHistory::query()
            ->where('accounts_payable_id', $accountsPayable->getKey())
            ->where('entry_type', PurchaseHistoryEntryType::PAGO_CUENTA_POR_PAGAR)
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        $payments = $histories->map(static function (PurchaseHistory $history): array {
            return [
                'paid_at' => $history->paid_at?->format('d/m/Y H:i') ?? '—',
                'payment_method' => PurchaseHistoryPaymentMethod::label($history->payment_method),
                'payment_form' => PurchaseHistoryPaymentForm::label($history->payment_form),
                'amount_paid_usd' => round((float) ($history->amount_paid_usd ?? 0), 2),
                'amount_paid_ves' => round((float) ($history->amount_paid_ves ?? 0), 2),
                'bcv_rate' => $history->bcv_rate_at_payment !== null
                    ? round((float) $history->bcv_rate_at_payment, 4)
                    : null,
                'payment_reference' => filled($history->payment_reference)
                    ? (string) $history->payment_reference
                    : null,
                'notes' => filled($history->notes) ? (string) $history->notes : null,
                'created_by' => filled($history->created_by) ? (string) $history->created_by : null,
                'retention_amount_ves' => $history->retention_amount_ves !== null
                    ? round((float) $history->retention_amount_ves, 2)
                    : null,
                'retention_voucher_number' => $history->retention_voucher_number !== null
                    ? (int) $history->retention_voucher_number
                    : null,
            ];
        });

        $taxSnapshot = AccountsPayableInvoiceTaxSnapshot::for($accountsPayable);

        return [
            'accounts_payable' => $accountsPayable,
            'branch_name' => $accountsPayable->branch?->name ?? '—',
            'purchase_number' => $accountsPayable->purchase?->purchase_number
                ?? $taxSnapshot->purchaseNumber,
            'status_label' => AccountsPayableStatus::label($accountsPayable->status),
            'tax_snapshot' => $taxSnapshot,
            'amount_payable_ves' => AccountsPayableInvoiceTaxSnapshot::amountPayableVes($accountsPayable),
            'payments' => $payments,
            'total_paid_usd' => round((float) $payments->sum('amount_paid_usd'), 2),
            'total_paid_ves' => round((float) $payments->sum('amount_paid_ves'), 2),
            'has_payment_proof' => filled($accountsPayable->payment_proof_path),
            'generated_at' => now()->format('d/m/Y H:i'),
            'generated_by' => $actor instanceof User
                ? (string) ($actor->email ?? $actor->name ?? 'usuario_'.$actor->getKey())
                : 'sistema',
        ];
    }

    /**
     * @param  Collection<int, AccountsPayable>  $accountsPayables
     * @return array{
     *     rows: Collection<int, array{
     *         accounts_payable: AccountsPayable,
     *         branch_name: string,
     *         purchase_number: string|null,
     *         amount_payable_ves: float,
     *         total_paid_usd: float,
     *         total_paid_ves: float,
     *         payment_reference: string|null,
     *         paid_at: string,
     *         has_payment_proof: bool,
     *         tax_retained_ves: float|null,
     *     }>,
     *     count: int,
     *     total_amount_payable_ves: float,
     *     total_paid_usd: float,
     *     total_paid_ves: float,
     *     total_tax_retained_ves: float,
     *     generated_at: string,
     *     generated_by: string,
     * }
     */
    public function buildMany(Collection $accountsPayables, ?User $actor = null): array
    {
        $rows = $accountsPayables
            ->unique(static fn (AccountsPayable $record): int => (int) $record->getKey())
            ->sortBy([
                ['paid_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->map(function (AccountsPayable $accountsPayable) use ($actor): array {
                $detail = $this->build($accountsPayable, $actor);

                return [
                    'accounts_payable' => $accountsPayable,
                    'branch_name' => $detail['branch_name'],
                    'purchase_number' => $detail['purchase_number'],
                    'amount_payable_ves' => $detail['amount_payable_ves'],
                    'total_paid_usd' => $detail['total_paid_usd'],
                    'total_paid_ves' => $detail['total_paid_ves'],
                    'payment_reference' => filled($accountsPayable->payment_reference)
                        ? (string) $accountsPayable->payment_reference
                        : null,
                    'paid_at' => $accountsPayable->paid_at?->format('d/m/Y H:i') ?? '—',
                    'has_payment_proof' => $detail['has_payment_proof'],
                    'tax_retained_ves' => $detail['tax_snapshot']->taxRetainedVes,
                ];
            });

        return [
            'rows' => $rows,
            'count' => $rows->count(),
            'total_amount_payable_ves' => round((float) $rows->sum('amount_payable_ves'), 2),
            'total_paid_usd' => round((float) $rows->sum('total_paid_usd'), 2),
            'total_paid_ves' => round((float) $rows->sum('total_paid_ves'), 2),
            'total_tax_retained_ves' => round((float) $rows->sum(
                static fn (array $row): float => (float) ($row['tax_retained_ves'] ?? 0),
            ), 2),
            'generated_at' => now()->format('d/m/Y H:i'),
            'generated_by' => $actor instanceof User
                ? (string) ($actor->email ?? $actor->name ?? 'usuario_'.$actor->getKey())
                : 'sistema',
        ];
    }
}
