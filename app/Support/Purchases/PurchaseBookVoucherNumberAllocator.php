<?php

namespace App\Support\Purchases;

use App\Models\PurchaseBook;
use Illuminate\Support\Carbon;

/**
 * Número de comprobante SENIAT: YYYY + MM + secuencia de 8 dígitos.
 *
 * La secuencia es por mes de la factura. El primer valor de un mes concreto
 * puede venir de config (p. ej. 20260800000120); el resto de meses arranca en 1
 * (p. ej. 20270100000001).
 */
final class PurchaseBookVoucherNumberAllocator
{
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
