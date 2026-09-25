<?php

namespace App\Services\Finance;

use App\Models\PurchaseBook;
use App\Models\PurchaseHistory;
use App\Models\PurchaseLedger;
use App\Services\Audit\AuditLogger;
use App\Support\Purchases\PurchaseBookVoucherNumberAllocator;
use Illuminate\Support\Facades\DB;

final class PurchaseRetentionVoucherRepairService
{
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
            foreach ($assignments as $assignment) {
                if ($assignment['old_voucher'] === $assignment['new_voucher']) {
                    continue;
                }

                $updated = PurchaseBook::query()
                    ->whereKey($assignment['book_id'])
                    ->update(['voucher_number' => $assignment['new_voucher']]);

                if ($updated === 0) {
                    $result['errors'][] = 'Retención #'.$assignment['book_id'].' no existe.';

                    continue;
                }

                if ($assignment['purchase_id'] > 0) {
                    PurchaseLedger::query()
                        ->where('purchase_id', $assignment['purchase_id'])
                        ->whereNotNull('retention_voucher_number')
                        ->update(['retention_voucher_number' => $assignment['new_voucher']]);

                    PurchaseHistory::query()
                        ->where('purchase_id', $assignment['purchase_id'])
                        ->whereNotNull('retention_voucher_number')
                        ->update(['retention_voucher_number' => $assignment['new_voucher']]);
                }

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
     * @return list<array{book_id: int, purchase_id: int, old_voucher: int, new_voucher: int, tax_period: string}>
     */
    private function buildAssignments(int $septemberStart): array
    {
        $books = PurchaseBook::query()
            ->where('tax_period', '2026/09')
            ->orderBy('invoice_date')
            ->orderBy('supplier_rif')
            ->orderBy('id')
            ->get();

        /** @var array<string, list<PurchaseBook>> $groups */
        $groups = [];

        foreach ($books as $book) {
            $key = ($book->supplier_rif ?: $book->supplier_name)
                .'|'.($book->invoice_date?->toDateString() ?? 'sin-fecha');
            $groups[$key][] = $book;
        }

        $nextVoucher = $septemberStart;
        $assignments = [];

        foreach ($groups as $rows) {
            foreach (array_chunk($rows, PurchaseBookVoucherNumberAllocator::InvoicesPerVoucher) as $chunk) {
                foreach ($chunk as $book) {
                    $assignments[] = [
                        'book_id' => (int) $book->getKey(),
                        'purchase_id' => (int) ($book->purchase_id ?? 0),
                        'old_voucher' => (int) $book->voucher_number,
                        'new_voucher' => $nextVoucher,
                        'tax_period' => '2026/09',
                    ];
                }

                $nextVoucher++;
            }
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
