<?php

namespace App\Services\Purchases;

use App\Enums\PurchaseStatus;
use App\Models\AccountsPayable;
use App\Models\Purchase;
use App\Models\PurchaseBook;
use App\Models\PurchaseHistory;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use App\Support\Finance\AccountsPayableStatus;
use App\Support\Purchases\PurchaseHistoryEntryType;
use App\Support\Purchases\PurchaseHistoryPaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Ficha del proveedor: datos generales + todo el historial de compras, pagos, comprobantes y retenciones,
 * acotado a las sucursales del usuario ({@see BranchAuthScope}). Alimenta el slide-over y el PDF.
 */
final class SupplierDossierBuilder
{
    /**
     * Compras que no cuentan para los totales (no generan deuda real con el proveedor).
     *
     * @var list<PurchaseStatus>
     */
    private const EXCLUDED_FROM_TOTALS = [
        PurchaseStatus::Draft,
        PurchaseStatus::Cancelled,
        PurchaseStatus::Annulled,
    ];

    /**
     * @return array{
     *     supplier: Supplier,
     *     is_branch_scoped: bool,
     *     summary: array{
     *         purchases_count: int,
     *         purchased_usd: float,
     *         purchased_ves: float,
     *         paid_usd: float,
     *         paid_ves: float,
     *         pending_usd: float,
     *         pending_ves: float,
     *         pending_invoices_count: int,
     *         retained_ves: float,
     *         retentions_count: int,
     *         paid_invoices_count: int,
     *         payment_proofs_count: int,
     *         first_purchase_at: string|null,
     *         last_purchase_at: string|null,
     *         last_payment_at: string|null,
     *     },
     *     purchases: list<array{purchase_id: int, accounts_payable_id: int|null, purchase_number: string, invoice_number: string|null, branch_name: string, date: string|null, status: string, is_excluded: bool, document_total: string, total_usd: float|null, payment_status: string}>,
     *     payments: list<array{purchase_id: int|null, accounts_payable_id: int|null, paid_at: string|null, invoice_number: string|null, branch_name: string, payment_method: string, payment_bank: string|null, payment_reference: string|null, amount_usd: float, amount_ves: float}>,
     *     payment_proofs: list<array{accounts_payable_id: int, invoice_number: string|null, branch_name: string, paid_at: string|null, payment_reference: string|null, amount_usd: float, proof_url: string|null, proof_is_pdf: bool}>,
     *     retentions: list<array{purchase_id: int, purchase_book_id: int, voucher_number: int|null, issue_date: string|null, tax_period: string|null, invoice_number: string|null, invoice_date: string|null, taxable_base_ves: float, tax_caused_ves: float, retention_percent: float|null, tax_retained_ves: float}>,
     * }
     */
    public function build(Supplier $supplier, ?User $viewer = null): array
    {
        $supplier->loadMissing('bankAccounts');

        $purchaseIdsQuery = $this->scopedPurchasesQuery($supplier)->select('purchases.id');

        $purchases = $this->scopedPurchasesQuery($supplier)
            ->with(['branch:id,name', 'accountsPayable:id,purchase_id,status,purchase_total_usd'])
            ->orderByDesc('registered_in_system_date')
            ->orderByDesc('id')
            ->get();

        $payments = PurchaseHistory::query()
            ->where('entry_type', PurchaseHistoryEntryType::PAGO_CUENTA_POR_PAGAR)
            ->whereIn('purchase_id', $purchaseIdsQuery)
            ->with('branch:id,name')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $accountsPayables = AccountsPayable::query()
            ->whereIn('purchase_id', $purchaseIdsQuery)
            ->with('branch:id,name')
            ->get();

        $retentions = PurchaseBook::query()
            ->whereIn('purchase_id', $purchaseIdsQuery)
            ->where('tax_retained_ves', '>', 0)
            ->orderByDesc('issue_date')
            ->orderByDesc('voucher_number')
            ->get();

        $purchasesById = $purchases->keyBy('id');
        $countedAccountsPayables = $accountsPayables->filter(
            fn (AccountsPayable $ap): bool => ! $this->isExcludedPurchase($purchasesById->get($ap->purchase_id)),
        );
        $pendingAccountsPayables = $countedAccountsPayables->where('status', AccountsPayableStatus::POR_PAGAR);
        $paymentProofs = $accountsPayables
            ->filter(static fn (AccountsPayable $ap): bool => filled($ap->payment_proof_path))
            ->sortByDesc(static fn (AccountsPayable $ap): int => (int) ($ap->paid_at?->timestamp ?? 0));
        $countedPurchases = $purchases->reject(fn (Purchase $purchase): bool => $this->isExcludedPurchase($purchase));
        $purchaseDates = $countedPurchases
            ->map(static fn (Purchase $purchase): mixed => $purchase->registered_in_system_date ?? $purchase->created_at)
            ->filter();

        return [
            'supplier' => $supplier,
            'is_branch_scoped' => $viewer instanceof User && ! $viewer->isAdministrator() && ! $viewer->isDeliveryUser(),
            'summary' => [
                'purchases_count' => $countedPurchases->count(),
                'purchased_usd' => round((float) $countedAccountsPayables->sum('purchase_total_usd'), 2),
                'purchased_ves' => round((float) $countedAccountsPayables->sum('purchase_total_ves_at_issue'), 2),
                'paid_usd' => round((float) $payments->sum('amount_paid_usd'), 2),
                'paid_ves' => round((float) $payments->sum('amount_paid_ves'), 2),
                'pending_usd' => round((float) $pendingAccountsPayables->sum('remaining_principal_usd'), 2),
                'pending_ves' => round((float) $pendingAccountsPayables->sum('current_balance_ves'), 2),
                'pending_invoices_count' => $pendingAccountsPayables->count(),
                'retained_ves' => round((float) $retentions->sum('tax_retained_ves'), 2),
                'retentions_count' => $retentions->count(),
                'paid_invoices_count' => $payments->pluck('accounts_payable_id')->filter()->unique()->count(),
                'payment_proofs_count' => $paymentProofs->count(),
                'first_purchase_at' => $this->formatDate($purchaseDates->min()),
                'last_purchase_at' => $this->formatDate($purchaseDates->max()),
                'last_payment_at' => $payments->first()?->paid_at?->format('d/m/Y'),
            ],
            'purchases' => $purchases->map(fn (Purchase $purchase): array => [
                'purchase_id' => (int) $purchase->id,
                'accounts_payable_id' => $purchase->accountsPayable !== null ? (int) $purchase->accountsPayable->id : null,
                'purchase_number' => (string) $purchase->purchase_number,
                'invoice_number' => filled($purchase->supplier_invoice_number) ? (string) $purchase->supplier_invoice_number : null,
                'branch_name' => $purchase->branch?->name ?? '—',
                'date' => $this->formatDate($purchase->registered_in_system_date ?? $purchase->created_at),
                'status' => $purchase->status instanceof PurchaseStatus ? $purchase->status->label() : '—',
                'is_excluded' => $this->isExcludedPurchase($purchase),
                'document_total' => $purchase->documentMoneyPrefix().' '.number_format((float) $purchase->total, 2, ',', '.'),
                'total_usd' => $purchase->accountsPayable !== null
                    ? round((float) $purchase->accountsPayable->purchase_total_usd, 2)
                    : null,
                'payment_status' => $purchase->accountsPayable !== null
                    ? AccountsPayableStatus::label($purchase->accountsPayable->status)
                    : 'Sin cuenta por pagar',
            ])->values()->all(),
            'payments' => $payments->map(static fn (PurchaseHistory $history): array => [
                'purchase_id' => $history->purchase_id !== null ? (int) $history->purchase_id : null,
                'accounts_payable_id' => $history->accounts_payable_id !== null ? (int) $history->accounts_payable_id : null,
                'paid_at' => $history->paid_at?->format('d/m/Y H:i'),
                'invoice_number' => filled($history->supplier_invoice_number) ? (string) $history->supplier_invoice_number : null,
                'branch_name' => $history->branch?->name ?? '—',
                'payment_method' => PurchaseHistoryPaymentMethod::label($history->payment_method),
                'payment_bank' => $history->paymentBankLabel(),
                'payment_reference' => filled($history->payment_reference) ? (string) $history->payment_reference : null,
                'amount_usd' => round((float) $history->amount_paid_usd, 2),
                'amount_ves' => round((float) $history->amount_paid_ves, 2),
            ])->values()->all(),
            'payment_proofs' => $paymentProofs->map(fn (AccountsPayable $ap): array => [
                'accounts_payable_id' => (int) $ap->id,
                'invoice_number' => filled($ap->supplier_invoice_number) ? (string) $ap->supplier_invoice_number : null,
                'branch_name' => $ap->branch?->name ?? '—',
                'paid_at' => $ap->paid_at?->format('d/m/Y'),
                'payment_reference' => filled($ap->payment_reference) ? (string) $ap->payment_reference : null,
                'amount_usd' => round((float) $ap->purchase_total_usd, 2),
                'proof_url' => $this->paymentProofUrl($ap),
                'proof_is_pdf' => str_ends_with(mb_strtolower((string) $ap->payment_proof_path), '.pdf'),
            ])->values()->all(),
            'retentions' => $retentions->map(fn (PurchaseBook $book): array => [
                'purchase_id' => (int) $book->purchase_id,
                'purchase_book_id' => (int) $book->id,
                'voucher_number' => $book->voucher_number !== null ? (int) $book->voucher_number : null,
                'issue_date' => $this->formatDate($book->issue_date),
                'tax_period' => filled($book->tax_period) ? (string) $book->tax_period : null,
                'invoice_number' => filled($book->invoice_number) ? (string) $book->invoice_number : null,
                'invoice_date' => $this->formatDate($book->invoice_date),
                'taxable_base_ves' => round((float) $book->taxable_base_ves, 2),
                'tax_caused_ves' => round((float) $book->tax_caused_ves, 2),
                'retention_percent' => $book->seniat_retention_percent !== null ? (float) $book->seniat_retention_percent : null,
                'tax_retained_ves' => round((float) $book->tax_retained_ves, 2),
            ])->values()->all(),
        ];
    }

    /**
     * Indicadores livianos para el header de la vista (sin cargar el historial completo).
     *
     * @return array{purchased_usd: float, pending_usd: float, pending_invoices_count: int, last_purchase_at: string|null}
     */
    public function headerSummary(Supplier $supplier): array
    {
        $countedPurchaseIds = $this->scopedPurchasesQuery($supplier)
            ->where(function (Builder $query): void {
                $query->whereNull('status')->orWhereNotIn('status', array_map(
                    static fn (PurchaseStatus $status): string => $status->value,
                    self::EXCLUDED_FROM_TOTALS,
                ));
            })
            ->select('purchases.id');

        $pending = AccountsPayable::query()
            ->whereIn('purchase_id', $countedPurchaseIds)
            ->where('status', AccountsPayableStatus::POR_PAGAR)
            ->selectRaw('COALESCE(SUM(remaining_principal_usd), 0) as total, COUNT(*) as invoices')
            ->first();

        $lastPurchaseAt = $this->scopedPurchasesQuery($supplier)
            ->whereIn('purchases.id', $countedPurchaseIds)
            ->max('registered_in_system_date');

        return [
            'purchased_usd' => round((float) AccountsPayable::query()
                ->whereIn('purchase_id', $countedPurchaseIds)
                ->sum('purchase_total_usd'), 2),
            'pending_usd' => round((float) ($pending?->getAttribute('total') ?? 0), 2),
            'pending_invoices_count' => (int) ($pending?->getAttribute('invoices') ?? 0),
            'last_purchase_at' => $this->formatDate($lastPurchaseAt),
        ];
    }

    /**
     * @return Builder<Purchase>
     */
    private function scopedPurchasesQuery(Supplier $supplier): Builder
    {
        return BranchAuthScope::apply(
            Purchase::query()->where('purchases.supplier_id', $supplier->getKey()),
        );
    }

    private function isExcludedPurchase(?Purchase $purchase): bool
    {
        if (! $purchase instanceof Purchase) {
            return false;
        }

        return in_array($purchase->status, self::EXCLUDED_FROM_TOTALS, true);
    }

    private function paymentProofUrl(AccountsPayable $ap): ?string
    {
        if (! filled($ap->payment_proof_path)) {
            return null;
        }

        return Storage::disk('public')->url((string) $ap->payment_proof_path);
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        return filled($value) ? Carbon::parse((string) $value)->format('d/m/Y') : null;
    }
}
