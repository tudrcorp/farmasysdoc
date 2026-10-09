<?php

namespace App\Console\Commands;

use App\Services\Finance\PurchaseRetentionVoucherMonthRenumberer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('purchases:renumber-retention-vouchers-month {--month= : Mes de los comprobantes (YYYY-MM)} {--start= : Secuencia (8 dígitos) del primer comprobante del mes, p. ej. 204} {--dry-run : Solo muestra el plan, no escribe}')]
#[Description('Renumera los comprobantes de retención de un mes desde la secuencia indicada (Retenciones, Libro de Compras e histórico)')]
final class RenumberPurchaseRetentionVouchersCommand extends Command
{
    public function handle(PurchaseRetentionVoucherMonthRenumberer $renumberer): int
    {
        $month = (string) $this->option('month');
        $start = (int) $this->option('start');

        if (preg_match('/^(\d{4})-(\d{2})$/', $month, $matches) !== 1 || (int) $matches[2] < 1 || (int) $matches[2] > 12) {
            $this->error('Indique --month con formato YYYY-MM.');

            return self::FAILURE;
        }

        if ($start < 1 || $start > 99999999) {
            $this->error('Indique --start entre 1 y 99999999.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Simulación: no se escribirá nada.');
        }

        $result = $renumberer->renumber($matches[1].$matches[2], $start, $dryRun);

        $this->table(
            ['Antes', 'Después', 'Facturas', 'Proveedor'],
            collect($result['assignments'])
                ->map(fn (array $row): array => [$row['old_voucher'], $row['new_voucher'], $row['invoices'], $row['supplier_name']])
                ->all(),
        );

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        if ($result['errors'] !== []) {
            return self::FAILURE;
        }

        $this->info(($dryRun ? 'Comprobantes a renumerar: ' : 'Comprobantes renumerados: ').count($result['assignments']));

        return self::SUCCESS;
    }
}
