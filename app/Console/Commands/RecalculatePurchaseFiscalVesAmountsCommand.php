<?php

namespace App\Console\Commands;

use App\Services\Finance\PurchaseFiscalVesAmountsRecalculator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('purchases:recalculate-fiscal-ves-amounts {--from= : Fecha de factura inicial (Y-m-d)} {--to= : Fecha de factura final (Y-m-d)} {--dry-run : Solo muestra los cambios, no escribe}')]
#[Description('Recalcula base, IVA, total y retención en Bs del Libro de Compras y Retenciones (IVA sobre la base en Bs)')]
final class RecalculatePurchaseFiscalVesAmountsCommand extends Command
{
    public function handle(PurchaseFiscalVesAmountsRecalculator $recalculator): int
    {
        try {
            $from = Carbon::createFromFormat('Y-m-d', (string) $this->option('from'))->startOfDay();
            $to = Carbon::createFromFormat('Y-m-d', (string) $this->option('to'))->startOfDay();
        } catch (\Throwable) {
            $this->error('Indique --from y --to con formato Y-m-d.');

            return self::FAILURE;
        }

        if ($from->greaterThan($to)) {
            $this->error('La fecha inicial no puede ser mayor que la final.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Simulación: no se escribirá nada.');
        }

        $result = $recalculator->recalculate($from, $to, $dryRun);

        $this->table(
            ['Nº', 'Proveedor', 'Base antes', 'Base después', 'IVA antes', 'IVA después', 'Total antes', 'Total después', 'Ret. antes', 'Ret. después'],
            collect($result['changes'])
                ->map(fn (array $row): array => [
                    $row['operation_number'],
                    $row['supplier_name'],
                    number_format($row['before']['base'], 2, ',', '.'),
                    number_format($row['after']['base'], 2, ',', '.'),
                    number_format($row['before']['tax'], 2, ',', '.'),
                    number_format($row['after']['tax'], 2, ',', '.'),
                    number_format($row['before']['total'], 2, ',', '.'),
                    number_format($row['after']['total'], 2, ',', '.'),
                    $row['before']['retained'] !== null ? number_format($row['before']['retained'], 2, ',', '.') : '—',
                    $row['after']['retained'] !== null ? number_format($row['after']['retained'], 2, ',', '.') : '—',
                ])
                ->all(),
        );

        $this->info(($dryRun ? 'Facturas a corregir: ' : 'Facturas corregidas: ').count($result['changes']));
        $this->info('Sin cambios: '.$result['unchanged']);

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
