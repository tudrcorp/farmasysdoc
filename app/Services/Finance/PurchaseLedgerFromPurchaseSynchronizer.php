<?php

namespace App\Services\Finance;

use App\Enums\PurchaseEntryCurrency;
use App\Enums\PurchaseLedgerDocumentType;
use App\Models\Purchase;
use App\Models\PurchaseBook;
use App\Models\PurchaseLedger;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\DefaultVatRate;
use App\Support\Fiscal\VenezuelanRifFormatter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Genera filas del Libro de Compras al confirmar una compra.
 * Si hay retención (PurchaseBook), crea también la fila COMPROBANTE DE RETENCION
 * y completa columnas 16–18 en la factura.
 */
final class PurchaseLedgerFromPurchaseSynchronizer
{
    public function __construct(
        private readonly PurchaseMonetarySnapshotBuilder $snapshotBuilder,
    ) {}

    /**
     * @return list<PurchaseLedger>
     */
    public function syncFromPurchase(Purchase $purchase, ?PurchaseBook $retention = null): array
    {
        $purchase->loadMissing('supplier');
        $retention ??= PurchaseBook::query()->where('purchase_id', $purchase->id)->first();

        $existingFactura = PurchaseLedger::query()
            ->where('purchase_id', $purchase->id)
            ->where('document_type', PurchaseLedgerDocumentType::Factura)
            ->first();

        if ($existingFactura !== null) {
            return $this->completeExistingLedgerRows($purchase, $existingFactura, $retention);
        }

        $invoiceDate = Carbon::parse(
            $purchase->supplier_invoice_date ?? $purchase->registered_in_system_date ?? now()
        )->startOfDay();

        $rateAtInvoice = $this->snapshotBuilder->resolveRateForPurchase($purchase, $invoiceDate);
        if ($rateAtInvoice === null || $rateAtInvoice <= 0) {
            AuditLogger::record(
                event: 'purchase_ledger_skipped_missing_bcv_rate',
                description: 'Libro de Compras: no se generó fila por falta de tasa BCV para la fecha de factura.',
                auditableType: Purchase::class,
                auditableId: (string) $purchase->getKey(),
                auditableLabel: $purchase->purchase_number,
                properties: [
                    'purchase_id' => $purchase->id,
                    'invoice_date' => $invoiceDate->toDateString(),
                ],
            );

            return [];
        }

        $taxPeriod = $invoiceDate->format('Y/m');
        $invoiceTotalVes = $this->amountToVes((float) $purchase->total, $purchase, $rateAtInvoice);
        $taxableBaseDocument = (float) ($purchase->net_taxable_after_document_discount
            ?? $purchase->subtotal_taxable_amount
            ?? 0);
        $exemptDocument = (float) ($purchase->net_exempt_after_document_discount
            ?? $purchase->subtotal_exempt_amount
            ?? 0);
        $taxableBaseVes = $this->amountToVes($taxableBaseDocument, $purchase, $rateAtInvoice);
        $exemptVes = $this->amountToVes($exemptDocument, $purchase, $rateAtInvoice);
        $taxCausedVes = $this->amountToVes((float) $purchase->tax_total, $purchase, $rateAtInvoice);

        $supplier = $purchase->supplier;
        $supplierName = $supplier !== null
            ? (filled($supplier->legal_name) ? (string) $supplier->legal_name : $supplier->displayName())
            : '—';
        $supplierRif = VenezuelanRifFormatter::format($supplier?->tax_id);

        $invoiceNumber = trim((string) ($purchase->supplier_invoice_number ?? ''));
        if ($invoiceNumber === '') {
            $invoiceNumber = (string) $purchase->purchase_number;
        }

        $actor = Auth::user()?->email
            ?? Auth::user()?->name
            ?? 'sistema';

        $created = DB::transaction(function () use (
            $purchase,
            $retention,
            $taxPeriod,
            $invoiceDate,
            $invoiceTotalVes,
            $taxableBaseVes,
            $exemptVes,
            $taxCausedVes,
            $supplierName,
            $supplierRif,
            $invoiceNumber,
            $actor,
        ): array {
            $rows = [];

            $facturaOp = $this->nextOperationNumber($taxPeriod);
            $rows[] = PurchaseLedger::query()->create([
                'purchase_id' => $purchase->id,
                'purchase_book_id' => $retention?->id,
                'tax_period' => $taxPeriod,
                'operation_number' => $facturaOp,
                'document_type' => PurchaseLedgerDocumentType::Factura,
                'document_number' => $invoiceNumber,
                'control_number' => $purchase->supplier_control_number,
                'supplier_name' => $supplierName,
                'supplier_tax_id' => $supplierRif !== '' ? $supplierRif : (string) ($purchase->supplier?->tax_id ?? ''),
                'taxpayer_type' => null,
                'total_with_vat_and_exempt_ves' => $invoiceTotalVes,
                'exempt_amount_ves' => $exemptVes > 0 ? $exemptVes : null,
                'export_amount_ves' => null,
                'taxable_base_ves' => $taxableBaseVes,
                'tax_caused_ves' => $taxCausedVes,
                'taxable_base_reduced_ves' => null,
                'tax_reduced_ves' => null,
                'vat_rate_percent' => DefaultVatRate::percent(),
                'retention_voucher_issued_at' => $retention?->issue_date?->toDateString(),
                'retention_voucher_number' => $retention?->voucher_number,
                'retention_amount_ves' => $retention !== null ? $retention->tax_retained_ves : null,
                'invoice_date' => $invoiceDate->toDateString(),
                'created_by' => $actor,
            ]);

            if ($retention !== null) {
                $retOp = $this->nextOperationNumber($taxPeriod);
                $rows[] = PurchaseLedger::query()->create([
                    'purchase_id' => $purchase->id,
                    'purchase_book_id' => $retention->id,
                    'tax_period' => $taxPeriod,
                    'operation_number' => $retOp,
                    'document_type' => PurchaseLedgerDocumentType::ComprobanteDeRetencion,
                    'document_number' => (string) $retention->voucher_number,
                    'control_number' => $purchase->supplier_control_number,
                    'supplier_name' => $supplierName,
                    'supplier_tax_id' => $supplierRif !== '' ? $supplierRif : (string) ($purchase->supplier?->tax_id ?? ''),
                    'taxpayer_type' => null,
                    'total_with_vat_and_exempt_ves' => $invoiceTotalVes,
                    'exempt_amount_ves' => null,
                    'export_amount_ves' => null,
                    'taxable_base_ves' => $taxableBaseVes,
                    'tax_caused_ves' => $taxCausedVes,
                    'taxable_base_reduced_ves' => null,
                    'tax_reduced_ves' => null,
                    'vat_rate_percent' => DefaultVatRate::percent(),
                    'retention_voucher_issued_at' => $retention->issue_date?->toDateString(),
                    'retention_voucher_number' => $retention->voucher_number,
                    'retention_amount_ves' => $retention->tax_retained_ves,
                    'invoice_date' => $invoiceDate->toDateString(),
                    'created_by' => $actor,
                ]);
            }

            return $rows;
        });

        AuditLogger::forModel(
            $purchase,
            'purchase_ledger_registered',
            [
                'origen' => 'sistema_tras_guardar_compra',
                'rows' => count($created),
                'has_retention' => $retention !== null,
                'retention_voucher_number' => $retention?->voucher_number,
                'tax_period' => $taxPeriod,
            ],
        );

        return $created;
    }

    /**
     * Al imprimir el comprobante de retención, actualiza la fecha de emisión en el Libro.
     */
    public function markRetentionIssuedForPurchaseIds(array $purchaseIds, string $issuedAt): void
    {
        $purchaseIds = array_values(array_unique(array_filter($purchaseIds)));
        if ($purchaseIds === []) {
            return;
        }

        PurchaseLedger::query()
            ->whereIn('purchase_id', $purchaseIds)
            ->update(['retention_voucher_issued_at' => $issuedAt]);
    }

    /**
     * Completa filas ya existentes: actualiza datos de retención en FACTURA
     * y crea COMPROBANTE DE RETENCION si falta.
     *
     * @return list<PurchaseLedger>
     */
    private function completeExistingLedgerRows(
        Purchase $purchase,
        PurchaseLedger $factura,
        ?PurchaseBook $retention,
    ): array {
        $rows = [$factura];

        if ($retention === null) {
            $existingRetentionDoc = PurchaseLedger::query()
                ->where('purchase_id', $purchase->id)
                ->where('document_type', PurchaseLedgerDocumentType::ComprobanteDeRetencion)
                ->first();

            if ($existingRetentionDoc !== null) {
                $rows[] = $existingRetentionDoc;
            }

            return $rows;
        }

        $factura->forceFill([
            'purchase_book_id' => $retention->id,
            'retention_voucher_issued_at' => $retention->issue_date?->toDateString(),
            'retention_voucher_number' => $retention->voucher_number,
            'retention_amount_ves' => $retention->tax_retained_ves,
        ])->save();

        $existingRetentionDoc = PurchaseLedger::query()
            ->where('purchase_id', $purchase->id)
            ->where('document_type', PurchaseLedgerDocumentType::ComprobanteDeRetencion)
            ->first();

        if ($existingRetentionDoc !== null) {
            $existingRetentionDoc->forceFill([
                'purchase_book_id' => $retention->id,
                'document_number' => (string) $retention->voucher_number,
                'retention_voucher_issued_at' => $retention->issue_date?->toDateString(),
                'retention_voucher_number' => $retention->voucher_number,
                'retention_amount_ves' => $retention->tax_retained_ves,
            ])->save();
            $rows[] = $existingRetentionDoc;

            return $rows;
        }

        $taxPeriod = (string) $factura->tax_period;
        $invoiceTotalVes = (float) $factura->total_with_vat_and_exempt_ves;
        $taxableBaseVes = (float) ($factura->taxable_base_ves ?? 0);
        $taxCausedVes = (float) ($factura->tax_caused_ves ?? 0);

        $comprobante = DB::transaction(function () use (
            $purchase,
            $retention,
            $factura,
            $taxPeriod,
            $invoiceTotalVes,
            $taxableBaseVes,
            $taxCausedVes,
        ): PurchaseLedger {
            $retOp = $this->nextOperationNumber($taxPeriod);

            return PurchaseLedger::query()->create([
                'purchase_id' => $purchase->id,
                'purchase_book_id' => $retention->id,
                'tax_period' => $taxPeriod,
                'operation_number' => $retOp,
                'document_type' => PurchaseLedgerDocumentType::ComprobanteDeRetencion,
                'document_number' => (string) $retention->voucher_number,
                'control_number' => $factura->control_number,
                'supplier_name' => $factura->supplier_name,
                'supplier_tax_id' => $factura->supplier_tax_id,
                'taxpayer_type' => null,
                'total_with_vat_and_exempt_ves' => $invoiceTotalVes,
                'exempt_amount_ves' => null,
                'export_amount_ves' => null,
                'taxable_base_ves' => $taxableBaseVes,
                'tax_caused_ves' => $taxCausedVes,
                'taxable_base_reduced_ves' => null,
                'tax_reduced_ves' => null,
                'vat_rate_percent' => $factura->vat_rate_percent,
                'retention_voucher_issued_at' => $retention->issue_date?->toDateString(),
                'retention_voucher_number' => $retention->voucher_number,
                'retention_amount_ves' => $retention->tax_retained_ves,
                'invoice_date' => $factura->invoice_date?->toDateString(),
                'created_by' => Auth::user()?->email ?? Auth::user()?->name ?? 'sistema',
            ]);
        });

        $rows[] = $comprobante;

        return $rows;
    }

    private function nextOperationNumber(string $taxPeriod): int
    {
        $last = PurchaseLedger::query()
            ->where('tax_period', $taxPeriod)
            ->lockForUpdate()
            ->orderByDesc('operation_number')
            ->first();

        return $last !== null ? ((int) $last->operation_number) + 1 : 1;
    }

    private function amountToVes(float $amount, Purchase $purchase, float $rateAtInvoice): float
    {
        if ($purchase->entryCurrency() === PurchaseEntryCurrency::VES) {
            return round($amount, 2);
        }

        return round($amount * $rateAtInvoice, 2);
    }
}
