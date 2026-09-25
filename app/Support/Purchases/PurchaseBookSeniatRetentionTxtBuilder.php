<?php

namespace App\Support\Purchases;

use App\Enums\PurchaseLedgerDocumentType;
use App\Models\PurchaseBook;
use App\Models\PurchaseLedger;
use App\Services\Finance\PurchaseLedgerBookReportBuilder;
use Illuminate\Support\Collection;

/**
 * Arma el TXT de retención de IVA que exige el SENIAT (columnas A–P, separado por tabulaciones).
 */
final class PurchaseBookSeniatRetentionTxtBuilder
{
    public function __construct(
        private readonly PurchaseLedgerBookReportBuilder $periodRanges,
    ) {}

    public function build(string $taxPeriod, string $half): string
    {
        [$from, $until] = $this->periodRanges->rangeFor($taxPeriod, $half);

        /** @var Collection<int, PurchaseBook> $books */
        $books = PurchaseBook::query()
            ->where('tax_period', $taxPeriod)
            ->whereDate('invoice_date', '>=', $from->toDateString())
            ->whereDate('invoice_date', '<=', $until->toDateString())
            ->orderBy('invoice_date')
            ->orderBy('operation_number')
            ->orderBy('id')
            ->get();

        $exemptByPurchase = PurchaseLedger::query()
            ->whereIn('purchase_id', $books->pluck('purchase_id')->filter()->unique()->all())
            ->where('document_type', PurchaseLedgerDocumentType::Factura)
            ->pluck('exempt_amount_ves', 'purchase_id');

        $lines = $books
            ->map(function (PurchaseBook $book) use ($exemptByPurchase): string {
                $exempt = $book->purchase_id !== null
                    ? (float) ($exemptByPurchase->get($book->purchase_id) ?? 0)
                    : 0.0;

                $columns = [
                    $this->compactRif((string) $book->retention_agent_rif),
                    str_replace('/', '', (string) $book->tax_period),
                    $book->invoice_date?->format('Y-m-d') ?? '',
                    'C',
                    '01',
                    $this->compactRif((string) $book->supplier_rif),
                    $this->documentNumber((string) $book->invoice_number),
                    trim((string) $book->invoice_control_number),
                    $this->money($book->invoice_total_ves),
                    $this->money($book->taxable_base_ves),
                    $this->money($book->tax_retained_ves),
                    '0',
                    (string) $book->voucher_number,
                    $this->money($exempt),
                    $this->money($book->vat_rate_percent ?? 16),
                    '0',
                ];

                return implode("\t", $columns);
            })
            ->all();

        if ($lines === []) {
            return '';
        }

        return implode("\r\n", $lines)."\r\n";
    }

    private function compactRif(string $rif): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $rif));
    }

    private function documentNumber(string $number): string
    {
        $number = trim($number);

        if (preg_match('/^\d+$/', $number) === 1) {
            return str_pad($number, 6, '0', STR_PAD_LEFT);
        }

        return $number;
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
