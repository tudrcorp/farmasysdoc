<?php

namespace App\Services\Finance;

use App\Enums\PurchaseLedgerDocumentType;
use App\Models\PurchaseBook;
use App\Models\PurchaseHistory;
use App\Models\PurchaseLedger;
use App\Services\Audit\AuditLogger;
use App\Support\Purchases\PurchaseBookVoucherNumberAllocator;
use Illuminate\Support\Facades\DB;

/**
 * Renumera los comprobantes de retención de un mes (YYYYMM) a partir de una secuencia dada,
 * conservando el orden y las agrupaciones de facturas, y propaga el número al Libro de Compras
 * y al histórico de compras.
 */
final class PurchaseRetentionVoucherMonthRenumberer
{
    /**
     * Desplazamiento a un rango sin comprobantes reales para que dos números nunca se mezclen
     * mientras se reasignan (las columnas son unsigned).
     */
    private const int TemporaryOffset = 500000000000000;

    /**
     * @return array{
     *     dry_run: bool,
     *     assignments: list<array{old_voucher: int, new_voucher: int, invoices: int, supplier_name: string}>,
     *     errors: list<string>
     * }
     */
    public function renumber(string $yearMonth, int $startSequence, bool $dryRun = false): array
    {
        $result = ['dry_run' => $dryRun, 'assignments' => [], 'errors' => []];

        $rangeStart = PurchaseBookVoucherNumberAllocator::compose($yearMonth, 0);
        $rangeEnd = PurchaseBookVoucherNumberAllocator::compose($yearMonth, 99999999);

        $vouchers = PurchaseBook::query()
            ->whereBetween('voucher_number', [$rangeStart, $rangeEnd])
            ->selectRaw('voucher_number, count(*) as invoices, min(supplier_name) as supplier_name')
            ->groupBy('voucher_number')
            ->orderBy('voucher_number')
            ->get();

        $sequence = $startSequence;
        foreach ($vouchers as $voucher) {
            $result['assignments'][] = [
                'old_voucher' => (int) $voucher->voucher_number,
                'new_voucher' => PurchaseBookVoucherNumberAllocator::compose($yearMonth, $sequence),
                'invoices' => (int) $voucher->getAttribute('invoices'),
                'supplier_name' => (string) $voucher->getAttribute('supplier_name'),
            ];
            $sequence++;
        }

        $oldNumbers = array_column($result['assignments'], 'old_voucher');
        $newNumbers = array_column($result['assignments'], 'new_voucher');
        $collisions = PurchaseBook::query()
            ->whereIn('voucher_number', $newNumbers)
            ->whereNotIn('voucher_number', $oldNumbers)
            ->pluck('voucher_number')
            ->unique()
            ->all();

        foreach ($collisions as $collision) {
            $result['errors'][] = 'El comprobante '.$collision.' ya existe y no pertenece a este mes.';
        }

        if ($dryRun || $result['errors'] !== [] || $result['assignments'] === []) {
            return $result;
        }

        DB::transaction(function () use ($result): void {
            foreach ($result['assignments'] as $assignment) {
                $this->moveVoucher($assignment['old_voucher'], $assignment['old_voucher'] + self::TemporaryOffset);
            }

            foreach ($result['assignments'] as $assignment) {
                $this->moveVoucher($assignment['old_voucher'] + self::TemporaryOffset, $assignment['new_voucher']);
            }
        });

        AuditLogger::record(
            event: 'purchase_retention_vouchers_renumbered',
            description: 'Retenciones: comprobantes del mes '.$yearMonth.' renumerados desde la secuencia '.$startSequence.'.',
            properties: [
                'year_month' => $yearMonth,
                'start_sequence' => $startSequence,
                'assignments' => array_map(
                    fn (array $assignment): array => ['antes' => $assignment['old_voucher'], 'despues' => $assignment['new_voucher']],
                    $result['assignments'],
                ),
            ],
        );

        return $result;
    }

    private function moveVoucher(int $from, int $to): void
    {
        PurchaseBook::query()
            ->where('voucher_number', $from)
            ->update(['voucher_number' => $to]);

        PurchaseLedger::query()
            ->where('retention_voucher_number', $from)
            ->update(['retention_voucher_number' => $to]);

        PurchaseLedger::query()
            ->where('document_type', PurchaseLedgerDocumentType::ComprobanteDeRetencion)
            ->where('document_number', (string) $from)
            ->update(['document_number' => (string) $to]);

        PurchaseHistory::query()
            ->where('retention_voucher_number', $from)
            ->update(['retention_voucher_number' => $to]);
    }
}
