<?php

namespace App\Support\Finance;

use App\Enums\PurchaseEntryCurrency;
use App\Models\AccountsPayable;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * IVA y retención SENIAT de una CxP: prioriza Retenciones (purchase_books);
 * si no hay fila, resuelve la compra por purchase_id o Nº de factura y calcula desde purchases.
 */
final class AccountsPayableInvoiceTaxSnapshot
{
    /**
     * @var array<int|string, self>
     */
    private static array $memo = [];

    public function __construct(
        public readonly ?float $taxCausedVes,
        public readonly ?float $taxRetainedVes,
        public readonly ?float $retentionPercent,
        public readonly ?float $vatRatePercent,
        public readonly ?int $purchaseId,
        public readonly ?string $purchaseNumber,
        public readonly ?string $supplierInvoiceNumber,
        public readonly string $source,
    ) {}

    public static function empty(?string $supplierInvoiceNumber = null): self
    {
        return new self(
            taxCausedVes: null,
            taxRetainedVes: null,
            retentionPercent: null,
            vatRatePercent: null,
            purchaseId: null,
            purchaseNumber: null,
            supplierInvoiceNumber: $supplierInvoiceNumber,
            source: 'none',
        );
    }

    public static function for(AccountsPayable $record): self
    {
        $memoKey = $record->getKey() ?? spl_object_id($record);

        if (isset(self::$memo[$memoKey])) {
            return self::$memo[$memoKey];
        }

        $record->loadMissing(['purchase.purchaseBook', 'purchase.supplier']);

        $purchase = $record->purchase ?? self::findPurchaseByInvoiceNumber($record->supplier_invoice_number);

        if ($purchase === null) {
            return self::$memo[$memoKey] = self::empty($record->supplier_invoice_number);
        }

        $purchase->loadMissing(['purchaseBook', 'supplier']);

        $book = $purchase->purchaseBook;
        if ($book !== null) {
            $percent = $purchase->supplier?->seniat_retention_percent !== null
                ? (float) $purchase->supplier->seniat_retention_percent
                : ($book->seniat_retention_percent !== null ? (float) $book->seniat_retention_percent : null);

            return self::$memo[$memoKey] = new self(
                taxCausedVes: (float) ($book->tax_caused_ves ?? 0),
                taxRetainedVes: (float) ($book->tax_retained_ves ?? 0),
                retentionPercent: $percent,
                vatRatePercent: $book->vat_rate_percent !== null ? (float) $book->vat_rate_percent : DefaultVatRate::percent(),
                purchaseId: (int) $purchase->getKey(),
                purchaseNumber: $purchase->purchase_number,
                supplierInvoiceNumber: $record->supplier_invoice_number ?: $purchase->supplier_invoice_number,
                source: 'purchase_book',
            );
        }

        $taxCausedVes = self::taxTotalToVes($purchase);
        $percent = $purchase->supplier?->seniat_retention_percent !== null
            ? (float) $purchase->supplier->seniat_retention_percent
            : null;
        $taxRetainedVes = $percent !== null
            ? round($taxCausedVes * ($percent / 100), 2)
            : null;

        return self::$memo[$memoKey] = new self(
            taxCausedVes: $taxCausedVes,
            taxRetainedVes: $taxRetainedVes,
            retentionPercent: $percent,
            vatRatePercent: DefaultVatRate::percent(),
            purchaseId: (int) $purchase->getKey(),
            purchaseNumber: $purchase->purchase_number,
            supplierInvoiceNumber: $record->supplier_invoice_number ?: $purchase->supplier_invoice_number,
            source: 'purchase',
        );
    }

    public static function sumTaxCausedForQuery(Builder $query): float
    {
        return round(self::snapshotsForQuery($query)->sum(
            fn (self $snapshot): float => (float) ($snapshot->taxCausedVes ?? 0),
        ), 2);
    }

    public static function sumTaxRetainedForQuery(Builder $query): float
    {
        return round(self::snapshotsForQuery($query)->sum(
            fn (self $snapshot): float => (float) ($snapshot->taxRetainedVes ?? 0),
        ), 2);
    }

    /**
     * Total factura (Bs, tasa emisión) menos retención SENIAT.
     */
    public static function amountPayableVes(AccountsPayable $record): float
    {
        $retained = (float) (self::for($record)->taxRetainedVes ?? 0);

        return round((float) $record->purchase_total_ves_at_issue - $retained, 2);
    }

    public static function sumAmountPayableForQuery(Builder $query): float
    {
        return round($query->clone()
            ->reorder()
            ->with(['purchase.purchaseBook', 'purchase.supplier'])
            ->get()
            ->sum(fn (AccountsPayable $record): float => self::amountPayableVes($record)), 2);
    }

    /**
     * Tasa BCV del día de registro/carga de la compra (Bs por USD).
     */
    public static function purchaseRegistrationBcvRate(AccountsPayable $record): ?float
    {
        $record->loadMissing(['purchase.purchaseBook']);

        $purchase = $record->purchase;

        if ($purchase !== null) {
            $officialRate = (float) ($purchase->official_usd_ves_rate ?? 0);
            if ($officialRate > 0) {
                return $officialRate;
            }

            $bookRate = (float) ($purchase->purchaseBook?->bcv_rate_at_invoice ?? 0);
            if ($bookRate > 0) {
                return $bookRate;
            }
        }

        $usdTotal = (float) $record->purchase_total_usd;

        if ($usdTotal > 0.00001) {
            $originalBalanceVes = (float) $record->original_balance_ves;
            if ($originalBalanceVes > 0) {
                return round($originalBalanceVes / $usdTotal, 8);
            }

            $vesAtIssue = (float) $record->purchase_total_ves_at_issue;
            if ($vesAtIssue > 0) {
                return round($vesAtIssue / $usdTotal, 8);
            }
        }

        return null;
    }

    /**
     * @return Collection<int, self>
     */
    private static function snapshotsForQuery(Builder $query): Collection
    {
        return $query->clone()
            ->reorder()
            ->with(['purchase.purchaseBook', 'purchase.supplier'])
            ->get()
            ->map(fn (AccountsPayable $record): self => self::for($record));
    }

    private static function findPurchaseByInvoiceNumber(mixed $invoiceNumber): ?Purchase
    {
        $invoiceNumber = trim((string) ($invoiceNumber ?? ''));

        if ($invoiceNumber === '') {
            return null;
        }

        return Purchase::query()
            ->with(['purchaseBook', 'supplier'])
            ->where('supplier_invoice_number', $invoiceNumber)
            ->latest('id')
            ->first();
    }

    private static function taxTotalToVes(Purchase $purchase): float
    {
        $taxTotal = round((float) $purchase->tax_total, 2);

        if ($purchase->entryCurrency() === PurchaseEntryCurrency::VES) {
            return $taxTotal;
        }

        $rate = (float) ($purchase->official_usd_ves_rate ?? 0);

        return $rate > 0 ? round($taxTotal * $rate, 2) : 0.0;
    }
}
