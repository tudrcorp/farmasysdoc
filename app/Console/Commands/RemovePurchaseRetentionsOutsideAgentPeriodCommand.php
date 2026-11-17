<?php

namespace App\Console\Commands;

use App\Services\Finance\PurchaseRetentionOutsideAgentPeriodRemover;
use App\Support\Finance\VatRetentionAgentPolicy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('purchases:remove-retentions-outside-agent-period {--dry-run : Solo muestra las facturas afectadas, no escribe}')]
#[Description('Quita la retención de IVA de facturas fechadas en el período en que la empresa no es agente de retención y recalcula sus cuentas por pagar')]
final class RemovePurchaseRetentionsOutsideAgentPeriodCommand extends Command
{
    public function handle(PurchaseRetentionOutsideAgentPeriodRemover $remover): int
    {
        $window = VatRetentionAgentPolicy::window();

        if ($window['suspended_from'] === null) {
            $this->info('No hay período sin retención configurado en «Administración financiera».');

            return self::SUCCESS;
        }

        $this->line('Sin retención desde: '.$window['suspended_from'].' · Retener de nuevo desde: '.($window['resumed_from'] ?? '—'));

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Simulación: no se escribirá nada.');
        }

        $result = $remover->remove($dryRun);

        $this->table(
            ['Compra', 'Fecha factura', 'Proveedor', 'Comprobante', 'Retención Bs'],
            collect($result['removed'])
                ->map(fn (array $row): array => [
                    $row['purchase_number'],
                    $row['invoice_date'],
                    $row['supplier_name'],
                    $row['voucher_number'],
                    number_format($row['tax_retained_ves'], 2, ',', '.'),
                ])
                ->all(),
        );

        $this->info(($dryRun ? 'Retenciones a quitar: ' : 'Retenciones quitadas: ').count($result['removed']));

        if (! $dryRun) {
            $this->info('Cuentas por pagar recalculadas: '.$result['accounts_payable_recalculated']);
        }

        foreach ($result['skipped_printed'] as $row) {
            $this->warn('Omitida (comprobante ya impreso): compra '.$row['purchase_number'].' · comprobante '.$row['voucher_number']);
        }

        return self::SUCCESS;
    }
}
