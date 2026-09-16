<?php

namespace App\Services\Finance;

use App\Enums\PurchaseLedgerDocumentType;
use App\Models\Purchase;
use App\Models\PurchaseBook;
use App\Models\PurchaseLedger;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PurchaseRetentionVoucherRepairService
{
    public function __construct(
        private readonly PurchaseBookFromPurchaseSynchronizer $retentionSynchronizer,
        private readonly PurchaseLedgerFromPurchaseSynchronizer $ledgerSynchronizer,
        private readonly PurchaseHistoryRetentionVoucherSynchronizer $historyRetentionSynchronizer,
    ) {}

    /**
     * Recrea las filas de Retenciones desde el Libro de Compras y renumera
     * los comprobantes de septiembre 2026 a partir de $septemberStart.
     *
     * @return array{
     *     dry_run: bool,
     *     recreated: int,
     *     september_renumbered: int,
     *     assignments: list<array{purchase_id: int, old_voucher: int, new_voucher: int, tax_period: string}>,
     *     errors: list<string>
     * }
     */
    public function repair(int $septemberStart = 20260900000148, bool $dryRun = false): array
    {
        $assignments = $this->buildAssignments($septemberStart);

        $result = [
            'dry_run' => $dryRun,
            'recreated' => 0,
            'september_renumbered' => 0,
            'assignments' => $assignments,
            'errors' => [],
        ];

        foreach ($assignments as $assignment) {
            if ($this->isSeptemberPeriod($assignment['tax_period']) && $assignment['old_voucher'] !== $assignment['new_voucher']) {
                $result['september_renumbered']++;
            }
        }

        if ($dryRun) {
            return $result;
        }

        DB::transaction(function () use ($assignments, &$result): void {
            PurchaseLedger::query()
                ->whereNotNull('purchase_book_id')
                ->update(['purchase_book_id' => null]);

            Schema::disableForeignKeyConstraints();
            try {
                PurchaseBook::query()->delete();
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            foreach ($assignments as $assignment) {
                $purchase = Purchase::query()
                    ->with('supplier')
                    ->find($assignment['purchase_id']);

                if ($purchase === null) {
                    $result['errors'][] = 'Compra #'.$assignment['purchase_id'].' no existe.';

                    continue;
                }

                $book = $this->retentionSynchronizer->syncFromPurchase(
                    $purchase,
                    $assignment['new_voucher'],
                );

                if ($book === null) {
                    $result['errors'][] = ($purchase->purchase_number ?? '#'.$purchase->id)
                        .': no se pudo recrear la retención (IVA o tasa BCV).';

                    continue;
                }

                if ((int) $book->voucher_number !== $assignment['new_voucher']) {
                    $book->forceFill(['voucher_number' => $assignment['new_voucher']])->save();
                    $this->historyRetentionSynchronizer->syncFromPurchaseBook($book);
                }

                $this->ledgerSynchronizer->syncFromPurchase($purchase, $book);
                $result['recreated']++;
            }

            if ($result['errors'] !== []) {
                throw new \RuntimeException(implode(' | ', $result['errors']));
            }
        });

        AuditLogger::record(
            event: 'purchase_retention_vouchers_repaired',
            description: 'Retenciones recreadas y correlativos de septiembre 2026 alineados al número inicial configurado.',
            properties: [
                'september_start' => $septemberStart,
                'recreated' => $result['recreated'],
                'september_renumbered' => $result['september_renumbered'],
            ],
        );

        return $result;
    }

    /**
     * @return list<array{purchase_id: int, old_voucher: int, new_voucher: int, tax_period: string}>
     */
    private function buildAssignments(int $septemberStart): array
    {
        $comprobantes = PurchaseLedger::query()
            ->where('document_type', PurchaseLedgerDocumentType::ComprobanteDeRetencion)
            ->orderBy('tax_period')
            ->orderBy('retention_voucher_number')
            ->orderBy('operation_number')
            ->get();

        $septemberIndex = 0;
        $assignments = [];

        foreach ($comprobantes as $row) {
            $oldVoucher = (int) $row->retention_voucher_number;
            $taxPeriod = (string) $row->tax_period;
            $newVoucher = $oldVoucher;

            if ($this->isSeptemberPeriod($taxPeriod)) {
                $newVoucher = $septemberStart + $septemberIndex;
                $septemberIndex++;
            }

            $assignments[] = [
                'purchase_id' => (int) $row->purchase_id,
                'old_voucher' => $oldVoucher,
                'new_voucher' => $newVoucher,
                'tax_period' => $taxPeriod,
            ];
        }

        usort(
            $assignments,
            fn (array $left, array $right): int => $left['new_voucher'] <=> $right['new_voucher'],
        );

        return $assignments;
    }

    private function isSeptemberPeriod(string $taxPeriod): bool
    {
        return $taxPeriod === '2026/09';
    }
}
