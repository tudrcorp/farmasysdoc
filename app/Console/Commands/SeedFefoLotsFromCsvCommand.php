<?php

namespace App\Console\Commands;

use App\Services\Inventory\FefoLotBalanceCsvImportService;
use Illuminate\Console\Command;

final class SeedFefoLotsFromCsvCommand extends Command
{
    protected $signature = 'inventory:seed-fefo-lots-from-csv
                            {file : Ruta absoluta del CSV}
                            {--branch-id= : ID de sucursal (69 Las Delicias, 70 San Bernardino)}
                            {--dry-run : Simular sin guardar cambios}
                            {--force : Actualizar lotes de apertura ya importados}
                            {--no-skip-with-lots : Procesar aunque ya exista saldo por lote de compras reales}';

    protected $description = 'Importa lotes FEFO de apertura desde CSV (sin modificar existencias en inventario)';

    public function handle(FefoLotBalanceCsvImportService $importService): int
    {
        $file = (string) $this->argument('file');
        $branchId = (int) $this->option('branch-id');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $skipWithLots = ! (bool) $this->option('no-skip-with-lots');

        if ($branchId <= 0) {
            $this->error('Debe indicar --branch-id=69 o --branch-id=70 (u otra sucursal activa).');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Modo simulación (--dry-run): no se guardará nada.');
        }

        try {
            $stats = $importService->importFromFile(
                filePath: $file,
                branchId: $branchId,
                dryRun: $dryRun,
                skipWithExistingLots: $skipWithLots,
                force: $force,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Sucursal ID: '.$stats['branch_id']);
        $this->line('Filas procesadas: '.$stats['processed']);

        if ($dryRun) {
            $this->line('Se importarían: '.$stats['seeded']);
        } else {
            $this->line('Lotes creados/actualizados: '.$stats['seeded']);
        }

        $this->line('Omitidas: '.$stats['skipped']);

        if ($stats['warnings'] !== []) {
            $this->newLine();
            $this->warn('Advertencias / omitidas ('.count($stats['warnings']).'):');
            foreach (array_slice($stats['warnings'], 0, 20) as $warning) {
                $this->line('  · '.$warning);
            }
            if (count($stats['warnings']) > 20) {
                $this->line('  … y '.(count($stats['warnings']) - 20).' más.');
            }
        }

        if ($stats['errors'] !== []) {
            $this->newLine();
            $this->error('Errores ('.count($stats['errors']).'):');
            foreach ($stats['errors'] as $error) {
                $this->line('  · '.$error);
            }

            $reportPath = $this->writeErrorReport($stats['errors'], $stats['warnings']);

            if ($reportPath !== null) {
                $this->line('Reporte: '.$reportPath);
            }

            return self::FAILURE;
        }

        if (! $dryRun && $stats['seeded'] > 0) {
            $this->newLine();
            $this->info('Importación completada. Opcional: php artisan inventory:sync-lot-balances');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    private function writeErrorReport(array $errors, array $warnings): ?string
    {
        if ($errors === [] && $warnings === []) {
            return null;
        }

        $dir = storage_path('app/import-reports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'fefo-import-'.now()->format('Y-m-d-His').'.log';
        $path = $dir.'/'.$filename;

        $lines = ['=== ERRORES ===', ...$errors, '', '=== ADVERTENCIAS ===', ...$warnings];
        file_put_contents($path, implode(PHP_EOL, $lines));

        return $path;
    }
}
