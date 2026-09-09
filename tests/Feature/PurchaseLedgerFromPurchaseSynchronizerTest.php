<?php

use App\Enums\PurchaseEntryCurrency;
use App\Enums\PurchaseLedgerDocumentType;
use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\PurchaseLedger;
use App\Models\Supplier;
use App\Services\Finance\PurchaseFiscalHistoryBackfillSynchronizer;
use App\Services\Finance\PurchaseLedgerFromPurchaseSynchronizer;
use App\Support\Purchases\PurchasePaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::fake([
        've.dolarapi.com/*' => Http::response([], 200),
    ]);
});

function purchaseForLedgerSync(array $overrides = []): Purchase
{
    $supplier = Supplier::factory()->create([
        'seniat_retention_percent' => 75,
    ]);

    return Purchase::factory()->create(array_merge([
        'supplier_id' => $supplier->id,
        'status' => PurchaseStatus::Received,
        'payment_status' => PurchasePaymentStatus::A_CREDITO,
        'entry_currency' => PurchaseEntryCurrency::VES,
        'official_usd_ves_rate' => 36.5,
        'supplier_invoice_number' => 'FAC-LEDGER-1',
        'supplier_control_number' => 'CTRL-1',
    ], $overrides));
}

it('omite del libro de compras las compras sin iva', function (): void {
    $purchase = purchaseForLedgerSync([
        'subtotal' => 100.00,
        'subtotal_exempt_amount' => 100.00,
        'subtotal_taxable_amount' => 0.0,
        'tax_total' => 0.0,
        'net_exempt_after_document_discount' => 100.00,
        'net_taxable_after_document_discount' => 0.0,
        'total' => 100.00,
        'declared_invoice_total' => 100.00,
    ]);

    $rows = app(PurchaseLedgerFromPurchaseSynchronizer::class)->syncFromPurchase($purchase);

    expect($rows)->toBe([])
        ->and(PurchaseLedger::query()->where('purchase_id', $purchase->id)->count())->toBe(0)
        ->and(DB::table('audit_logs')->where('event', 'purchase_ledger_skipped_no_vat')->exists())->toBeTrue();
});

it('registra en el libro de compras las facturas con iva', function (): void {
    $purchase = purchaseForLedgerSync([
        'subtotal' => 100.00,
        'subtotal_exempt_amount' => 0.0,
        'subtotal_taxable_amount' => 100.00,
        'tax_total' => 16.00,
        'net_exempt_after_document_discount' => 0.0,
        'net_taxable_after_document_discount' => 100.00,
        'total' => 116.00,
        'declared_invoice_total' => 116.00,
    ]);

    $rows = app(PurchaseLedgerFromPurchaseSynchronizer::class)->syncFromPurchase($purchase);

    expect($rows)->toHaveCount(1);

    $factura = $rows[0];
    expect($factura)->toBeInstanceOf(PurchaseLedger::class)
        ->and($factura->document_type)->toBe(PurchaseLedgerDocumentType::Factura)
        ->and((float) $factura->tax_caused_ves)->toBe(16.0)
        ->and((float) $factura->taxable_base_ves)->toBe(100.0)
        ->and(PurchaseLedger::query()->where('purchase_id', $purchase->id)->count())->toBe(1);
});

it('registra compras mixtas cuando hay iva aunque parte sea exenta', function (): void {
    $purchase = purchaseForLedgerSync([
        'subtotal' => 200.00,
        'subtotal_exempt_amount' => 100.00,
        'subtotal_taxable_amount' => 100.00,
        'tax_total' => 16.00,
        'net_exempt_after_document_discount' => 100.00,
        'net_taxable_after_document_discount' => 100.00,
        'total' => 216.00,
        'declared_invoice_total' => 216.00,
    ]);

    $rows = app(PurchaseLedgerFromPurchaseSynchronizer::class)->syncFromPurchase($purchase);

    expect($rows)->toHaveCount(1)
        ->and((float) $rows[0]->tax_caused_ves)->toBe(16.0)
        ->and((float) $rows[0]->exempt_amount_ves)->toBe(100.0);
});

it('no carga el libro de compras al hacer backfill de compras sin iva', function (): void {
    $purchase = purchaseForLedgerSync([
        'subtotal' => 80.00,
        'subtotal_exempt_amount' => 80.00,
        'subtotal_taxable_amount' => 0.0,
        'tax_total' => 0.0,
        'net_exempt_after_document_discount' => 80.00,
        'net_taxable_after_document_discount' => 0.0,
        'total' => 80.00,
        'declared_invoice_total' => 80.00,
    ]);

    $result = app(PurchaseFiscalHistoryBackfillSynchronizer::class)->run();

    expect($result->skippedNoVat)->toBeGreaterThanOrEqual(1)
        ->and($result->ledgerRowsCreated)->toBe(0)
        ->and(PurchaseLedger::query()->where('purchase_id', $purchase->id)->count())->toBe(0);
});
