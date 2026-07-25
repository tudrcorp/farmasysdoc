<?php

namespace App\Services\Finance;

use App\Enums\PurchaseEntryCurrency;
use App\Models\Purchase;
use App\Models\PurchaseBook;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\DefaultVatRate;
use App\Support\Fiscal\VenezuelanRifFormatter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Genera una fila de Retenciones al confirmar una compra con IVA
 * (idempotente por purchase_id; omite compras sin impuesto causado).
 */
final class PurchaseBookFromPurchaseSynchronizer
{
    public function __construct(
        private readonly PurchaseMonetarySnapshotBuilder $snapshotBuilder,
    ) {}

    public function syncFromPurchase(Purchase $purchase): ?PurchaseBook
    {
        $existing = PurchaseBook::query()->where('purchase_id', $purchase->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $purchase->loadMissing('supplier');

        $taxTotal = round((float) $purchase->tax_total, 2);
        if ($taxTotal <= 0) {
            AuditLogger::record(
                event: 'purchase_book_skipped_no_vat',
                description: 'Retenciones: no se generó fila porque la compra no tiene IVA (impuesto causado).',
                auditableType: Purchase::class,
                auditableId: (string) $purchase->getKey(),
                auditableLabel: $purchase->purchase_number,
                properties: [
                    'purchase_id' => $purchase->id,
                    'tax_total' => $purchase->tax_total,
                    'subtotal_taxable_amount' => $purchase->subtotal_taxable_amount,
                    'net_taxable_after_document_discount' => $purchase->net_taxable_after_document_discount,
                ],
            );

            return null;
        }

        $invoiceDate = Carbon::parse(
            $purchase->supplier_invoice_date ?? $purchase->registered_in_system_date ?? now()
        )->startOfDay();

        $rateAtInvoice = $this->snapshotBuilder->resolveRateForPurchase($purchase, $invoiceDate);
        if ($rateAtInvoice === null || $rateAtInvoice <= 0) {
            AuditLogger::record(
                event: 'purchase_book_skipped_missing_bcv_rate',
                description: 'Retenciones: no se generó fila por falta de tasa BCV para la fecha de factura.',
                auditableType: Purchase::class,
                auditableId: (string) $purchase->getKey(),
                auditableLabel: $purchase->purchase_number,
                properties: [
                    'purchase_id' => $purchase->id,
                    'invoice_date' => $invoiceDate->toDateString(),
                    'entry_currency' => $purchase->entry_currency?->value ?? (string) $purchase->entry_currency,
                ],
            );

            return null;
        }

        $taxPeriod = $invoiceDate->format('Y/m');
        $invoiceTotalVes = $this->amountToVes((float) $purchase->total, $purchase, $rateAtInvoice);
        $taxableBaseDocument = (float) ($purchase->net_taxable_after_document_discount
            ?? $purchase->subtotal_taxable_amount
            ?? 0);
        $taxableBaseVes = $this->amountToVes($taxableBaseDocument, $purchase, $rateAtInvoice);
        $taxCausedVes = $this->amountToVes((float) $purchase->tax_total, $purchase, $rateAtInvoice);

        $retentionPercent = (float) ($purchase->supplier?->seniat_retention_percent ?? 0);
        $taxRetainedVes = round($taxCausedVes * ($retentionPercent / 100), 2);

        $supplier = $purchase->supplier;
        $supplierName = $supplier !== null
            ? (filled($supplier->legal_name) ? (string) $supplier->legal_name : $supplier->displayName())
            : '—';
        $supplierRif = VenezuelanRifFormatter::format($supplier?->tax_id);
        $supplierAddress = $supplier !== null
            ? trim(collect([
                $supplier->address,
                $supplier->city,
                $supplier->state,
                $supplier->country,
            ])->filter(fn ($part): bool => filled($part))->implode(', '))
            : null;
        $supplierAddress = $supplierAddress === '' ? null : $supplierAddress;

        $invoiceNumber = trim((string) ($purchase->supplier_invoice_number ?? ''));
        if ($invoiceNumber === '') {
            $invoiceNumber = (string) $purchase->purchase_number;
        }

        $actor = Auth::user()?->email
            ?? Auth::user()?->name
            ?? 'sistema';

        $initialVoucher = (int) config('fiscal.purchase_book.initial_voucher_number', 20260700000058);

        $book = DB::transaction(function () use (
            $purchase,
            $taxPeriod,
            $invoiceDate,
            $rateAtInvoice,
            $invoiceTotalVes,
            $taxableBaseVes,
            $taxCausedVes,
            $taxRetainedVes,
            $retentionPercent,
            $supplierName,
            $supplierRif,
            $supplierAddress,
            $invoiceNumber,
            $actor,
            $initialVoucher,
        ): PurchaseBook {
            $again = PurchaseBook::query()
                ->where('purchase_id', $purchase->id)
                ->lockForUpdate()
                ->first();
            if ($again !== null) {
                return $again;
            }

            $lastVoucher = PurchaseBook::query()
                ->orderByDesc('voucher_number')
                ->lockForUpdate()
                ->first();
            $voucherNumber = $lastVoucher !== null
                ? ((int) $lastVoucher->voucher_number) + 1
                : $initialVoucher;

            $lastOperation = PurchaseBook::query()
                ->where('tax_period', $taxPeriod)
                ->orderByDesc('operation_number')
                ->lockForUpdate()
                ->first();
            $operationNumber = $lastOperation !== null
                ? ((int) $lastOperation->operation_number) + 1
                : 1;

            return PurchaseBook::query()->create([
                'purchase_id' => $purchase->id,
                'voucher_number' => $voucherNumber,
                'retention_agent_name' => (string) config('fiscal.retention_agent.name'),
                'retention_agent_rif' => (string) config('fiscal.retention_agent.rif'),
                'tax_period' => $taxPeriod,
                'retention_agent_address' => (string) config('fiscal.retention_agent.address'),
                'issue_date' => null,
                'supplier_name' => $supplierName,
                'supplier_rif' => $supplierRif !== '' ? $supplierRif : (string) ($purchase->supplier?->tax_id ?? '—'),
                'supplier_address' => $supplierAddress,
                'operation_number' => $operationNumber,
                'invoice_date' => $invoiceDate->toDateString(),
                'invoice_number' => $invoiceNumber,
                'invoice_control_number' => $purchase->supplier_control_number,
                'operation_class' => $operationNumber,
                'affected_control_number' => null,
                'invoice_total_ves' => $invoiceTotalVes,
                'purchases_without_vat_credit' => null,
                'taxable_base_ves' => $taxableBaseVes,
                'vat_rate_percent' => DefaultVatRate::percent(),
                'tax_caused_ves' => $taxCausedVes,
                'tax_retained_ves' => $taxRetainedVes,
                'bcv_rate_at_invoice' => $rateAtInvoice,
                'seniat_retention_percent' => $retentionPercent,
                'created_by' => $actor,
            ]);
        });

        AuditLogger::forModel(
            $book,
            'purchase_book_registered',
            [
                'origen' => 'sistema_tras_guardar_compra',
                'purchase_id' => $purchase->getKey(),
                'purchase_number' => $purchase->purchase_number,
                'voucher_number' => $book->voucher_number,
                'tax_period' => $book->tax_period,
                'operation_number' => $book->operation_number,
                'invoice_total_ves' => $book->invoice_total_ves,
                'tax_caused_ves' => $book->tax_caused_ves,
                'tax_retained_ves' => $book->tax_retained_ves,
                'bcv_rate_at_invoice' => $book->bcv_rate_at_invoice,
                'seniat_retention_percent' => $book->seniat_retention_percent,
            ],
        );

        app(PurchaseHistoryRetentionVoucherSynchronizer::class)->syncFromPurchaseBook($book);

        return $book;
    }

    private function amountToVes(float $amount, Purchase $purchase, float $rateAtInvoice): float
    {
        if ($purchase->entryCurrency() === PurchaseEntryCurrency::VES) {
            return round($amount, 2);
        }

        return round($amount * $rateAtInvoice, 2);
    }
}
