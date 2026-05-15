<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('inventory:sync-listado-quantities
    {file : Ruta absoluta al CSV (columnas: Código, Nombre, Existencia Actual)}
    {--branch-id=69 : ID de la sucursal cuyo inventario se actualiza}')]
#[Description('Actualiza solo quantity en inventories, emparejando Código del CSV con products.barcode')]
class SyncInventoryQuantitiesFromListadoCsvCommand extends Command
{
    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $branchId = (int) $this->option('branch-id');

        if (! is_file($file) || ! is_readable($file)) {
            $this->error('No se puede leer el archivo: '.$file);

            return self::FAILURE;
        }

        if (! Branch::query()->whereKey($branchId)->exists()) {
            $this->error('No existe sucursal con ID: '.$branchId);

            return self::FAILURE;
        }

        /** @var array<string, string> código (barcode) => cantidad como string del CSV */
        $quantityByBarcode = $this->parseCsv($file);

        if ($quantityByBarcode === []) {
            $this->error('El CSV no tiene filas de datos válidas.');

            return self::FAILURE;
        }

        $barcodes = array_keys($quantityByBarcode);

        /** @var Collection<string, int> barcode => product_id (primer match) */
        $productIdByBarcode = Product::query()
            ->whereIn('barcode', $barcodes)
            ->orderBy('id')
            ->get(['id', 'barcode'])
            ->unique('barcode')
            ->mapWithKeys(fn (Product $p): array => [(string) $p->barcode => (int) $p->id]);

        $missingProducts = array_values(array_diff($barcodes, $productIdByBarcode->keys()->all()));

        $productIds = $productIdByBarcode->values()->all();

        /** @var Collection<int, Inventory> */
        $inventories = Inventory::query()
            ->where('branch_id', $branchId)
            ->whereIn('product_id', $productIds)
            ->get(['id', 'product_id', 'quantity']);

        /** @var array<int, string> product_id => barcode */
        $barcodeByProductId = $productIdByBarcode->flip()->all();

        $processed = 0;
        $changed = 0;

        DB::transaction(function () use ($inventories, $barcodeByProductId, $quantityByBarcode, &$processed, &$changed): void {
            foreach ($inventories as $inventory) {
                $barcode = $barcodeByProductId[$inventory->product_id] ?? null;
                if ($barcode === null) {
                    continue;
                }
                $rawQty = $quantityByBarcode[$barcode] ?? null;
                if ($rawQty === null) {
                    continue;
                }
                $newQty = $this->normalizeQuantity($rawQty);
                $processed++;
                if ((string) $inventory->quantity === (string) $newQty) {
                    continue;
                }
                Inventory::query()->whereKey($inventory->id)->update(['quantity' => $newQty]);
                $changed++;
            }
        });

        $inventoryProductIds = $inventories->pluck('product_id')->all();
        $skippedNoInventory = [];
        foreach ($productIdByBarcode as $barcode => $productId) {
            if (! in_array($productId, $inventoryProductIds, true)) {
                $skippedNoInventory[] = $barcode;
            }
        }

        $this->info('Filas en CSV (códigos distintos): '.count($quantityByBarcode));
        $this->info('Productos encontrados por barcode: '.$productIdByBarcode->count());
        $this->info('Filas de inventario en sucursal que coincidieron: '.$inventories->count());
        $this->info('Filas de inventario alineadas con el CSV: '.$processed);
        $this->info('Filas con quantity actualizada en base de datos: '.$changed);

        if ($missingProducts !== []) {
            $this->warn('Códigos sin producto en catálogo ('.count($missingProducts).'): muestra '.implode(', ', array_slice($missingProducts, 0, 15)).(count($missingProducts) > 15 ? '…' : ''));
        }

        if ($skippedNoInventory !== []) {
            $this->warn('Producto existe pero sin fila de inventario en la sucursal ('.count($skippedNoInventory).'): muestra '.implode(', ', array_slice($skippedNoInventory, 0, 15)).(count($skippedNoInventory) > 15 ? '…' : ''));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        try {
            $headerRow = fgetcsv($handle, 0, ',', '"', '\\');
            if ($headerRow === false) {
                return [];
            }

            if (isset($headerRow[0]) && str_starts_with((string) $headerRow[0], "\xEF\xBB\xBF")) {
                $headerRow[0] = substr((string) $headerRow[0], 3);
            }

            $idxCode = $this->columnIndex($headerRow, 'Código');
            $idxQty = $this->columnIndex($headerRow, 'Existencia Actual');
            if ($idxCode === null || $idxQty === null) {
                $this->error('Encabezados esperados: Código, Existencia Actual. Encontrado: '.implode(' | ', $headerRow));

                return [];
            }

            $map = [];
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $code = isset($row[$idxCode]) ? trim((string) $row[$idxCode]) : '';
                if ($code === '') {
                    continue;
                }
                $qtyCell = isset($row[$idxQty]) ? trim((string) $row[$idxQty]) : '';
                $map[$code] = $qtyCell;
            }

            return $map;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string|null>|false  $headerRow
     */
    private function columnIndex(array|false $headerRow, string $name): ?int
    {
        if ($headerRow === false) {
            return null;
        }

        foreach ($headerRow as $i => $cell) {
            if (trim((string) $cell) === $name) {
                return (int) $i;
            }
        }

        return null;
    }

    private function normalizeQuantity(string $raw): string
    {
        $normalized = str_replace(',', '.', $raw);

        return is_numeric($normalized) ? (string) $normalized : '0';
    }
}
