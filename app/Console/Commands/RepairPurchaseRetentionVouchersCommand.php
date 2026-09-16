<?php

namespace App\Console\Commands;

use App\Services\Finance\PurchaseRetentionVoucherRepairService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('purchases:repair-retention-vouchers {--september-start=20260900000148 : Primer correlativo de septiembre 2026} {--dry-run : Solo muestra el plan, no escribe}')]
#[Description('Recrea Retenciones faltantes y renumera los comprobantes de septiembre 2026 desde el correlativo indicado')]
final class RepairPurchaseRetentionVouchersCommand extends Command
{
    public function handle(PurchaseRetentionVoucherRepairService $repairService): int
    {
        $septemberStart = (int) $this->option('september-start');
        $dryRun = (bool) $this->option('dry-run');

        if ($septemberStart <= 0) {
            $this->error('El correlativo inicial de septiembre no es válido.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Simulación: no se escribirá nada.');
        }

        try {
            $result = $repairService->repair($septemberStart, $dryRun);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Compra', 'Periodo', 'Antes', 'Después'],
            collect($result['assignments'])
                ->map(fn (array $row): array => [
                    $row['purchase_id'],
                    $row['tax_period'],
                    $row['old_voucher'],
                    $row['new_voucher'],
                ])
                ->all(),
        );

        $this->info('Retenciones recreadas: '.$result['recreated']);
        $this->info('Comprobantes de septiembre renumerados: '.$result['september_renumbered']);

        if ($result['errors'] !== []) {
            foreach ($result['errors'] as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
