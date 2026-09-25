<?php

namespace App\Support\Purchases;

use App\Models\PurchaseBook;
use Illuminate\Support\Carbon;

/**
 * Número de comprobante SENIAT: YYYY + MM + secuencia de 8 dígitos.
 *
 * La secuencia es por mes de la factura. El primer valor de un mes concreto
 * puede venir de config (p. ej. 20260900000148); el resto de meses arranca en 1
 * (p. ej. 20270100000001).
 */
final class PurchaseBookVoucherNumberAllocator
{
    public const int InvoicesPerVoucher = 5;

    /**
     * Reutiliza el comprobante del mismo proveedor y la misma fecha de factura
     * mientras tenga menos de 5 facturas. Si ya tiene 5, abre el siguiente correlativo.
     */
    public function forSupplierOnDate(string $supplierRif, Carbon $invoiceDate): int
    {
        $openVoucher = PurchaseBook::query()
            ->where('supplier_rif', $supplierRif)
            ->whereDate('invoice_date', $invoiceDate->toDateString())
            ->selectRaw('voucher_number, count(*) as invoices')
            ->groupBy('voucher_number')
            ->orderByDesc('voucher_number')
            ->lockForUpdate()
            ->get()
            ->first(fn (PurchaseBook $book): bool => (int) $book->getAttribute('invoices') < self::InvoicesPerVoucher);

        if ($openVoucher !== null) {
            return (int) $openVoucher->voucher_number;
        }

        return $this->nextForInvoiceDate($invoiceDate);
    }

    public function nextForInvoiceDate(Carbon $invoiceDate): int
    {
        $yearMonth = $invoiceDate->format('Ym');
        $rangeStart = (int) ($yearMonth.'00000000');
        $rangeEnd = (int) ($yearMonth.'99999999');

        $last = PurchaseBook::query()
            ->whereBetween('voucher_number', [$rangeStart, $rangeEnd])
            ->orderByDesc('voucher_number')
            ->lockForUpdate()
            ->value('voucher_number');

        if ($last !== null) {
            return (int) $last + 1;
        }

        $initial = (int) config('fiscal.purchase_book.initial_voucher_number');
        $initialYearMonth = substr((string) $initial, 0, 6);

        if ($yearMonth === $initialYearMonth) {
            return $initial;
        }

        return (int) ($yearMonth.'00000001');
    }
}
