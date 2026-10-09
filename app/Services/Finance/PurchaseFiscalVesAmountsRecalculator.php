<?php

namespace App\Services\Finance;

use App\Enums\PurchaseLedgerDocumentType;
use App\Models\AccountsPayable;
use App\Models\Purchase;
use App\Models\PurchaseBook;
use App\Models\PurchaseLedger;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\AccountsPayableStatus;
use App\Support\Finance\DefaultVatRate;
use App\Support\Finance\PurchaseFiscalVesAmounts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula base, IVA y retención en Bs del Libro de Compras y Retenciones de un rango de fechas de
 * factura con {@see PurchaseFiscalVesAmounts}, partiendo del total de la factura (A) y el exento (C)
 * ya registrados en el libro (no se reconvierten con la tasa): base = (A − C) ÷ 1,16; IVA = base × 16%;
 * retención según el proveedor. Propaga la retención al histórico y a las cuentas por pagar pendientes.
 */
final class PurchaseFiscalVesAmountsRecalculator
{
    public function __construct(
        private readonly PurchaseHistoryRetentionVoucherSynchronizer $historyRetentionSynchronizer,
        private readonly AccountsPayableCurrentBalanceRecalculator $accountsPayableRecalculator,
    ) {}

    /**
     * @return array{
     *     changes: list<array{
     *         purchase_id: int,
     *         operation_number: int,
     *         supplier_name: string,
     *         before: array{total: float, exempt: float, base: float, tax: float, retained: float|null},
     *         after: array{total: float, exempt: float, base: float, tax: float, retained: float|null}
     *     }>,
     *     unchanged: int,
     *     errors: list<string>
     * }
     */
    public function recalculate(Carbon $from, Carbon $to, bool $dryRun = false): array
    {
        $result = ['changes' => [], 'unchanged' => 0, 'errors' => []];

        $facturas = PurchaseLedger::query()
            ->where('document_type', PurchaseLedgerDocumentType::Factura)
            ->whereDate('invoice_date', '>=', $from->toDateString())
            ->whereDate('invoice_date', '<=', $to->toDateString())
            ->whereNotNull('purchase_id')
            ->with(['purchase.supplier', 'purchase.purchaseBook'])
            ->orderBy('operation_number')
            ->get();

        foreach ($facturas as $factura) {
            $purchase = $factura->purchase;
            if (! $purchase instanceof Purchase) {
                $result['errors'][] = 'Libro #'.$factura->operation_number.': compra no encontrada.';

                continue;
            }

            $book = $purchase->purchaseBook;
            $amounts = PurchaseFiscalVesAmounts::fromInvoiceAmounts(
                invoiceTotalDocument: (float) $factura->total_with_vat_and_exempt_ves,
                exemptDocument: (float) ($factura->exempt_amount_ves ?? 0),
                rateToVes: 1.0,
                vatRatePercent: $factura->vat_rate_percent !== null ? (float) $factura->vat_rate_percent : DefaultVatRate::percent(),
            );

            $retentionPercent = $book !== null ? $this->retentionPercent($purchase, $book) : null;
            $retainedVes = $retentionPercent !== null ? $amounts->retainedVes($retentionPercent) : null;

            $before = [
                'total' => (float) $factura->total_with_vat_and_exempt_ves,
                'exempt' => (float) ($factura->exempt_amount_ves ?? 0),
                'base' => (float) ($factura->taxable_base_ves ?? 0),
                'tax' => (float) ($factura->tax_caused_ves ?? 0),
                'retained' => $book !== null ? (float) $book->tax_retained_ves : null,
            ];
            $after = [
                'total' => $amounts->totalVes,
                'exempt' => $amounts->exemptVes,
                'base' => $amounts->taxableBaseVes,
                'tax' => $amounts->taxCausedVes,
                'retained' => $retainedVes,
            ];

            if ($this->sameAmounts($before, $after) && ($book === null || $this->bookMatches($book, $amounts, $retainedVes))) {
                $result['unchanged']++;

                continue;
            }

            $result['changes'][] = [
                'purchase_id' => (int) $purchase->getKey(),
                'operation_number' => (int) $factura->operation_number,
                'supplier_name' => (string) $factura->supplier_name,
                'before' => $before,
                'after' => $after,
            ];

            if ($dryRun) {
                continue;
            }

            $this->apply($purchase, $book, $amounts, $retainedVes, $before, $after);
        }

        return $result;
    }

    /**
     * @param  array{total: float, exempt: float, base: float, tax: float, retained: float|null}  $before
     * @param  array{total: float, exempt: float, base: float, tax: float, retained: float|null}  $after
     */
    private function apply(
        Purchase $purchase,
        ?PurchaseBook $book,
        PurchaseFiscalVesAmounts $amounts,
        ?float $retainedVes,
        array $before,
        array $after,
    ): void {
        DB::transaction(function () use ($purchase, $book, $amounts, $retainedVes): void {
            PurchaseLedger::query()
                ->where('purchase_id', $purchase->getKey())
                ->where('document_type', PurchaseLedgerDocumentType::Factura)
                ->update([
                    'total_with_vat_and_exempt_ves' => $amounts->totalVes,
                    'exempt_amount_ves' => $amounts->exemptVes > 0 ? $amounts->exemptVes : null,
                    'taxable_base_ves' => $amounts->taxableBaseVes,
                    'tax_caused_ves' => $amounts->taxCausedVes,
                    'retention_amount_ves' => $book !== null ? $retainedVes : null,
                ]);

            PurchaseLedger::query()
                ->where('purchase_id', $purchase->getKey())
                ->where('document_type', PurchaseLedgerDocumentType::ComprobanteDeRetencion)
                ->update([
                    'total_with_vat_and_exempt_ves' => $amounts->totalVes,
                    'taxable_base_ves' => $amounts->taxableBaseVes,
                    'tax_caused_ves' => $amounts->taxCausedVes,
                    'retention_amount_ves' => $retainedVes,
                ]);

            if ($book === null) {
                return;
            }

            $book->forceFill([
                'invoice_total_ves' => $amounts->totalVes,
                'taxable_base_ves' => $amounts->taxableBaseVes,
                'tax_caused_ves' => $amounts->taxCausedVes,
                'tax_retained_ves' => $retainedVes,
            ])->save();

            $this->historyRetentionSynchronizer->syncFromPurchaseBook($book);
        });

        if ($book !== null) {
            $purchase->unsetRelation('purchaseBook');

            AccountsPayable::query()
                ->where('purchase_id', $purchase->getKey())
                ->where('status', AccountsPayableStatus::POR_PAGAR)
                ->get()
                ->each(fn (AccountsPayable $accountsPayable): array => $this->accountsPayableRecalculator
                    ->recalculate($accountsPayable, audit: false));
        }

        AuditLogger::forModel(
            $purchase,
            'purchase_fiscal_ves_amounts_recalculated',
            [
                'origen' => 'recalculo_iva_sobre_base_ves',
                'purchase_book_id' => $book?->getKey(),
                'antes' => $before,
                'despues' => $after,
            ],
        );
    }

    private function retentionPercent(Purchase $purchase, PurchaseBook $book): ?float
    {
        if ($book->seniat_retention_percent !== null) {
            return (float) $book->seniat_retention_percent;
        }

        $supplierPercent = $purchase->supplier?->seniat_retention_percent;

        return $supplierPercent !== null ? (float) $supplierPercent : null;
    }

    /**
     * @param  array{total: float, exempt: float, base: float, tax: float, retained: float|null}  $before
     * @param  array{total: float, exempt: float, base: float, tax: float, retained: float|null}  $after
     */
    private function sameAmounts(array $before, array $after): bool
    {
        foreach ($after as $key => $value) {
            if (round((float) $value, 2) !== round((float) ($before[$key] ?? 0), 2)) {
                return false;
            }
        }

        return true;
    }

    private function bookMatches(PurchaseBook $book, PurchaseFiscalVesAmounts $amounts, ?float $retainedVes): bool
    {
        return round((float) $book->invoice_total_ves, 2) === round($amounts->totalVes, 2)
            && round((float) $book->taxable_base_ves, 2) === round($amounts->taxableBaseVes, 2)
            && round((float) $book->tax_caused_ves, 2) === round($amounts->taxCausedVes, 2)
            && round((float) $book->tax_retained_ves, 2) === round((float) $retainedVes, 2);
    }
}
