<?php

namespace App\Support\Purchases;

use App\Models\PurchaseBook;
use Illuminate\Support\Carbon;

/**
 * Número de comprobante SENIAT: YYYY + MM + secuencia de 8 dígitos.
 *
 * El prefijo YYYYMM es el mes de la factura y la secuencia es continua entre meses
 * (p. ej. 20260900000195 → 20261000000196); no se reinicia al cambiar de mes.
 * Si aún no hay comprobantes, arranca en la secuencia de config (fiscal.purchase_book.initial_voucher_number).
 */
final class PurchaseBookVoucherNumberAllocator
{
    public const int InvoicesPerVoucher = 5;

    private const int SequenceModulus = 100000000;

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
        $lastSequence = $this->lastSequence();

        if ($lastSequence === null) {
            $lastSequence = max(0, self::sequenceOf((int) config('fiscal.purchase_book.initial_voucher_number')) - 1);
        }

        return self::compose($invoiceDate->format('Ym'), $lastSequence + 1);
    }

    public static function sequenceOf(int $voucherNumber): int
    {
        return $voucherNumber % self::SequenceModulus;
    }

    public static function compose(string $yearMonth, int $sequence): int
    {
        return (int) ($yearMonth.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT));
    }

    private function lastSequence(): ?int
    {
        $last = PurchaseBook::query()
            ->lockForUpdate()
            ->selectRaw('MAX(voucher_number % '.self::SequenceModulus.') as last_sequence')
            ->value('last_sequence');

        return $last !== null ? (int) $last : null;
    }
}
