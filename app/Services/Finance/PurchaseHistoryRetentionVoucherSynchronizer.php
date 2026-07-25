<?php

namespace App\Services\Finance;

use App\Models\PurchaseBook;
use App\Models\PurchaseHistory;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Propaga datos del comprobante de retención IVA al histórico de compras.
 */
final class PurchaseHistoryRetentionVoucherSynchronizer
{
    /**
     * Copia número y monto de retención del módulo Retenciones a las filas del histórico de esa compra.
     * No modifica la fecha de emisión (solo se setea al imprimir).
     */
    public function syncFromPurchaseBook(PurchaseBook $book): int
    {
        $purchaseId = $book->purchase_id;
        if ($purchaseId === null) {
            return 0;
        }

        $updated = PurchaseHistory::query()
            ->where('purchase_id', $purchaseId)
            ->update([
                'retention_voucher_number' => $book->voucher_number,
                'retention_amount_ves' => $book->tax_retained_ves,
            ]);

        if ($updated > 0) {
            AuditLogger::record(
                event: 'purchase_history_retention_fields_synced_from_book',
                description: 'Histórico de compras: número y monto de retención sincronizados desde Retenciones.',
                auditableType: PurchaseBook::class,
                auditableId: (string) $book->getKey(),
                properties: [
                    'purchase_id' => $purchaseId,
                    'histories_updated' => $updated,
                    'retention_voucher_number' => $book->voucher_number,
                    'retention_amount_ves' => $book->tax_retained_ves,
                ],
            );
        }

        return (int) $updated;
    }

    /**
     * Al imprimir el comprobante: fija fecha de emisión (hoy) en Libro e histórico del grupo,
     * y asegura número/monto de retención en el histórico.
     *
     * @return Collection<int, PurchaseBook>
     */
    public function markIssuedOnPrint(string $supplierRif, string $invoiceDate): Collection
    {
        $date = Carbon::parse($invoiceDate)->toDateString();
        $issuedAt = now()->toDateString();

        return DB::transaction(function () use ($supplierRif, $date, $issuedAt): Collection {
            /** @var Collection<int, PurchaseBook> $books */
            $books = PurchaseBook::query()
                ->where('supplier_rif', $supplierRif)
                ->whereDate('invoice_date', $date)
                ->lockForUpdate()
                ->orderBy('operation_number')
                ->orderBy('voucher_number')
                ->get();

            if ($books->isEmpty()) {
                return $books;
            }

            PurchaseBook::query()
                ->whereIn('id', $books->modelKeys())
                ->update(['issue_date' => $issuedAt]);

            $purchaseIds = [];

            foreach ($books as $book) {
                $book->setAttribute('issue_date', $issuedAt);

                if ($book->purchase_id === null) {
                    continue;
                }

                $purchaseIds[] = (int) $book->purchase_id;

                PurchaseHistory::query()
                    ->where('purchase_id', $book->purchase_id)
                    ->update([
                        'retention_voucher_issued_at' => $issuedAt,
                        'retention_voucher_number' => $book->voucher_number,
                        'retention_amount_ves' => $book->tax_retained_ves,
                    ]);
            }

            $uniquePurchaseIds = array_values(array_unique($purchaseIds));

            if ($uniquePurchaseIds !== []) {
                app(PurchaseLedgerFromPurchaseSynchronizer::class)
                    ->markRetentionIssuedForPurchaseIds($uniquePurchaseIds, $issuedAt);
            }

            AuditLogger::record(
                event: 'purchase_retention_voucher_printed',
                description: 'Comprobante de retención IVA impreso: fecha de emisión actualizada en Retenciones, Libro de Compras e histórico.',
                properties: [
                    'supplier_rif' => $supplierRif,
                    'invoice_date' => $date,
                    'issue_date' => $issuedAt,
                    'purchase_ids' => $uniquePurchaseIds,
                    'voucher_numbers' => $books->pluck('voucher_number')->all(),
                ],
            );

            return $books;
        });
    }

    /**
     * @return array{
     *     retention_voucher_number: int|string|null,
     *     retention_amount_ves: float|string|null,
     *     retention_voucher_issued_at: string|null
     * }
     */
    public function attributesForPurchaseId(int $purchaseId): array
    {
        $book = PurchaseBook::query()
            ->where('purchase_id', $purchaseId)
            ->first();

        if ($book === null) {
            return [
                'retention_voucher_number' => null,
                'retention_amount_ves' => null,
                'retention_voucher_issued_at' => null,
            ];
        }

        return [
            'retention_voucher_number' => $book->voucher_number,
            'retention_amount_ves' => $book->tax_retained_ves,
            'retention_voucher_issued_at' => $book->issue_date?->toDateString(),
        ];
    }
}
