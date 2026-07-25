<?php

namespace App\Support\Purchases;

use App\Models\PurchaseBook;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Arma el payload del comprobante de retención IVA (Retenciones) para un grupo proveedor+fecha.
 */
final class PurchaseBookRetentionVoucherBuilder
{
    /**
     * @return array{
     *     books: Collection<int, PurchaseBook>,
     *     voucher_number: int|string,
     *     tax_period_year: string,
     *     tax_period_month: string,
     *     retention_agent_name: string,
     *     retention_agent_rif: string,
     *     retention_agent_address: string,
     *     issue_date: string,
     *     supplier_name: string,
     *     supplier_rif: string,
     *     supplier_address: string|null,
     *     total_invoice_ves: float,
     *     total_without_vat_credit: float,
     *     total_taxable_base_ves: float,
     *     total_tax_caused_ves: float,
     *     total_tax_retained_ves: float,
     *     balance_to_pay_ves: float,
     * }
     */
    public function build(string $supplierRif, string $invoiceDate): array
    {
        $date = Carbon::parse($invoiceDate)->toDateString();

        /** @var Collection<int, PurchaseBook> $books */
        $books = PurchaseBook::query()
            ->where('supplier_rif', $supplierRif)
            ->whereDate('invoice_date', $date)
            ->orderBy('operation_number')
            ->orderBy('voucher_number')
            ->get();

        if ($books->isEmpty()) {
            abort(404, 'No hay retenciones para ese proveedor y fecha.');
        }

        /** @var PurchaseBook $first */
        $first = $books->first();

        [$year, $month] = array_pad(explode('/', (string) $first->tax_period, 2), 2, '');

        $totalInvoice = round((float) $books->sum('invoice_total_ves'), 2);
        $totalWithoutCredit = round((float) $books->sum(fn (PurchaseBook $book): float => (float) ($book->purchases_without_vat_credit ?? 0)), 2);
        $totalBase = round((float) $books->sum('taxable_base_ves'), 2);
        $totalTax = round((float) $books->sum('tax_caused_ves'), 2);
        $totalRetained = round((float) $books->sum('tax_retained_ves'), 2);

        return [
            'books' => $books,
            'voucher_number' => $books->min('voucher_number') ?? $first->voucher_number,
            'tax_period_year' => $year,
            'tax_period_month' => $month,
            'retention_agent_name' => (string) $first->retention_agent_name,
            'retention_agent_rif' => (string) $first->retention_agent_rif,
            'retention_agent_address' => (string) $first->retention_agent_address,
            'issue_date' => now()->format('d/m/Y'),
            'supplier_name' => (string) $first->supplier_name,
            'supplier_rif' => (string) $first->supplier_rif,
            'supplier_address' => $first->supplier_address,
            'total_invoice_ves' => $totalInvoice,
            'total_without_vat_credit' => $totalWithoutCredit,
            'total_taxable_base_ves' => $totalBase,
            'total_tax_caused_ves' => $totalTax,
            'total_tax_retained_ves' => $totalRetained,
            'balance_to_pay_ves' => round($totalInvoice - $totalRetained, 2),
        ];
    }
}
