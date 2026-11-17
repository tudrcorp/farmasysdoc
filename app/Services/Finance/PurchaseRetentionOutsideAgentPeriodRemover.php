<?php

namespace App\Services\Finance;

use App\Enums\PurchaseLedgerDocumentType;
use App\Models\AccountsPayable;
use App\Models\PurchaseBook;
use App\Models\PurchaseHistory;
use App\Models\PurchaseLedger;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\AccountsPayableStatus;
use App\Support\Finance\VatRetentionAgentPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Quita la retención de IVA ya registrada en facturas cuya fecha de factura cae en el período
 * en que la empresa no es agente de retención: elimina la fila de Retenciones y su comprobante
 * en el Libro de Compras, limpia la retención del histórico y recalcula la cuenta por pagar
 * pendiente para que quede por el 100 % de la factura.
 *
 * No toca comprobantes ya impresos (con fecha de emisión en el histórico).
 */
final class PurchaseRetentionOutsideAgentPeriodRemover
{
    public function __construct(
        private readonly AccountsPayableCurrentBalanceRecalculator $accountsPayableRecalculator,
    ) {}

    /**
     * @return array{
     *     removed: list<array{purchase_id: int, purchase_number: string, invoice_date: string, supplier_name: string, voucher_number: int, tax_retained_ves: float}>,
     *     skipped_printed: list<array{purchase_id: int, purchase_number: string, voucher_number: int}>,
     *     accounts_payable_recalculated: int
     * }
     */
    public function remove(bool $dryRun = false): array
    {
        $result = ['removed' => [], 'skipped_printed' => [], 'accounts_payable_recalculated' => 0];

        $window = VatRetentionAgentPolicy::window();
        if ($window['suspended_from'] === null) {
            return $result;
        }

        $books = PurchaseBook::query()
            ->whereNotNull('purchase_id')
            ->whereHas('purchase', fn ($query) => $query->whereDate('supplier_invoice_date', '>=', $window['suspended_from']))
            ->with('purchase')
            ->orderBy('id')
            ->get()
            ->filter(fn (PurchaseBook $book): bool => ! VatRetentionAgentPolicy::retainsOnInvoiceDate($book->purchase?->supplier_invoice_date));

        foreach ($books as $book) {
            $purchase = $book->purchase;

            $printed = PurchaseHistory::query()
                ->where('purchase_id', $purchase->getKey())
                ->whereNotNull('retention_voucher_issued_at')
                ->exists();

            if ($printed) {
                $result['skipped_printed'][] = [
                    'purchase_id' => (int) $purchase->getKey(),
                    'purchase_number' => (string) $purchase->purchase_number,
                    'voucher_number' => (int) $book->voucher_number,
                ];

                continue;
            }

            $result['removed'][] = [
                'purchase_id' => (int) $purchase->getKey(),
                'purchase_number' => (string) $purchase->purchase_number,
                'invoice_date' => (string) $purchase->supplier_invoice_date?->toDateString(),
                'supplier_name' => (string) $book->supplier_name,
                'voucher_number' => (int) $book->voucher_number,
                'tax_retained_ves' => round((float) $book->tax_retained_ves, 2),
            ];

            if ($dryRun) {
                continue;
            }

            $snapshot = $book->only(['id', 'voucher_number', 'tax_period', 'operation_number', 'tax_retained_ves', 'seniat_retention_percent']);

            DB::transaction(function () use ($book, $purchase): void {
                PurchaseLedger::query()
                    ->where('purchase_id', $purchase->getKey())
                    ->where('document_type', PurchaseLedgerDocumentType::ComprobanteDeRetencion)
                    ->delete();

                PurchaseLedger::query()
                    ->where('purchase_id', $purchase->getKey())
                    ->update([
                        'purchase_book_id' => null,
                        'retention_voucher_issued_at' => null,
                        'retention_voucher_number' => null,
                        'retention_amount_ves' => null,
                    ]);

                PurchaseHistory::query()
                    ->where('purchase_id', $purchase->getKey())
                    ->update([
                        'retention_voucher_number' => null,
                        'retention_amount_ves' => null,
                    ]);

                $book->delete();
            });

            AuditLogger::forModel($purchase, 'purchase_retention_removed_outside_agent_period', [
                'origen' => 'empresa_no_agente_de_retencion',
                'supplier_invoice_date' => $purchase->supplier_invoice_date?->toDateString(),
                'vat_retention_window' => $window,
                'purchase_book' => $snapshot,
            ]);

            $purchase->unsetRelation('purchaseBook');

            AccountsPayable::query()
                ->where('purchase_id', $purchase->getKey())
                ->where('status', AccountsPayableStatus::POR_PAGAR)
                ->get()
                ->each(function (AccountsPayable $accountsPayable) use (&$result): void {
                    $this->accountsPayableRecalculator->recalculate($accountsPayable, audit: true);
                    $result['accounts_payable_recalculated']++;
                });
        }

        return $result;
    }
}
