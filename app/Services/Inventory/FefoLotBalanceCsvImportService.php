<?php

namespace App\Services\Inventory;

use App\Enums\PurchaseStatus;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryLotBalance;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Support\Inventory\InventoryQuantityFormat;
use App\Support\Inventory\PosInventoryStockFailureRegistrar;
use App\Support\Purchases\LotExpirationMonthYear;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Importa lotes FEFO de apertura desde CSV sin alterar existencias en {@see Inventory}.
 */
final class FefoLotBalanceCsvImportService
{
    public const SEED_PURCHASE_NUMBER_PREFIX = 'INV-FEFO-APERTURA-';

    /**
     * @return array{
     *     processed: int,
     *     seeded: int,
     *     skipped: int,
     *     errors: list<string>,
     *     warnings: list<string>,
     *     dry_run: bool,
     *     branch_id: int,
     * }
     */
    public function importFromFile(
        string $filePath,
        int $branchId,
        bool $dryRun = false,
        bool $skipWithExistingLots = true,
        bool $force = false,
    ): array {
        if ($branchId <= 0) {
            throw new RuntimeException('Debe indicar una sucursal válida (--branch-id).');
        }

        if (! Branch::query()->whereKey($branchId)->exists()) {
            throw new RuntimeException('No existe la sucursal ID '.$branchId.'.');
        }

        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException('No se puede leer el CSV: '.$filePath);
        }

        $rows = $this->parseCsv($filePath, $branchId);
        $stats = [
            'processed' => 0,
            'seeded' => 0,
            'skipped' => 0,
            'errors' => [],
            'warnings' => [],
            'dry_run' => $dryRun,
            'branch_id' => $branchId,
        ];

        if ($rows === []) {
            $stats['errors'][] = 'El CSV no contiene filas de datos.';

            return $stats;
        }

        if ($dryRun) {
            foreach ($rows as $index => $row) {
                $stats['processed']++;
                $outcome = $this->evaluateRow($row, $branchId, $skipWithExistingLots, $force, $index + 2);
                if ($outcome['action'] === 'seed') {
                    $stats['seeded']++;
                } else {
                    $this->mergeOutcome($stats, $outcome);
                }
            }

            return $stats;
        }

        DB::transaction(function () use ($rows, $branchId, $skipWithExistingLots, $force, &$stats): void {
            $purchase = $this->resolveSeedPurchase($branchId);

            foreach ($rows as $index => $row) {
                $stats['processed']++;
                $outcome = $this->evaluateRow($row, $branchId, $skipWithExistingLots, $force, $index + 2);

                if ($outcome['action'] !== 'seed') {
                    $this->mergeOutcome($stats, $outcome);

                    continue;
                }

                $this->persistSeedRow(
                    purchase: $purchase,
                    branchId: $branchId,
                    product: $outcome['product'],
                    expiration: $outcome['expiration'],
                    quantity: $outcome['quantity'],
                    force: $force,
                );

                $stats['seeded']++;
            }
        });

        return $stats;
    }

    /**
     * @return list<array{barcode: string, expiration: string, branch_id: int, quantity: ?float, line: int}>
     */
    private function parseCsv(string $filePath, int $defaultBranchId): array
    {
        $handle = fopen($filePath, 'r');
        if (! is_resource($handle)) {
            throw new RuntimeException('No se pudo abrir el CSV.');
        }

        $headerRow = fgetcsv($handle);
        if (! is_array($headerRow)) {
            fclose($handle);

            throw new RuntimeException('El CSV no tiene encabezado.');
        }

        $map = $this->mapHeaders($headerRow);
        if (! isset($map['barcode'], $map['expiration'])) {
            fclose($handle);

            throw new RuntimeException(
                'Encabezado inválido. Se requieren columnas codigo_barras y vencimiento_mm_yyyy.'
            );
        }

        $rows = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if (! is_array($row) || $this->isEmptyRow($row)) {
                continue;
            }

            $barcode = trim((string) ($row[$map['barcode']] ?? ''));
            $expirationRaw = trim((string) ($row[$map['expiration']] ?? ''));

            if ($barcode === '' && $expirationRaw === '') {
                continue;
            }

            $branchId = $defaultBranchId;
            if (isset($map['branch_id'])) {
                $branchCell = trim((string) ($row[$map['branch_id']] ?? ''));
                if ($branchCell !== '' && is_numeric($branchCell)) {
                    $branchId = (int) $branchCell;
                }
            }

            $quantity = null;
            if (isset($map['quantity'])) {
                $qtyCell = trim((string) ($row[$map['quantity']] ?? ''));
                if ($qtyCell !== '') {
                    $quantity = $this->parseQuantity($qtyCell);
                }
            }

            $rows[] = [
                'barcode' => $barcode,
                'expiration' => $this->normalizeExpirationInput($expirationRaw),
                'branch_id' => $branchId,
                'quantity' => $quantity,
                'line' => $lineNumber,
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string|null>  $headerRow
     * @return array<string, int>
     */
    private function mapHeaders(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $cell) {
            $key = $this->normalizeHeaderKey((string) $cell);

            if (in_array($key, ['codigo_barras', 'barcode', 'codigo', 'ean', 'codigo_de_barras'], true)) {
                $map['barcode'] = (int) $index;
            }

            if (in_array($key, ['vencimiento_mm_yyyy', 'vencimiento', 'expiry', 'lote_vencimiento', 'fecha_vencimiento'], true)) {
                $map['expiration'] = (int) $index;
            }

            if (in_array($key, ['sucursal_id', 'branch_id', 'sucursal'], true)) {
                $map['branch_id'] = (int) $index;
            }

            if (in_array($key, ['cantidad', 'quantity', 'qty', 'existencia'], true)) {
                $map['quantity'] = (int) $index;
            }
        }

        return $map;
    }

    private function normalizeHeaderKey(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = str_replace([' ', '-', '.'], '_', $header);

        return $header;
    }

    /**
     * @param  array{barcode: string, expiration: string, branch_id: int, quantity: ?float, line: int}  $row
     * @return array{
     *     action: 'seed'|'skip'|'error',
     *     message?: string,
     *     product?: Product,
     *     expiration?: string,
     *     quantity?: float,
     * }
     */
    private function evaluateRow(
        array $row,
        int $expectedBranchId,
        bool $skipWithExistingLots,
        bool $force,
        int $line,
    ): array {
        if ($row['branch_id'] !== $expectedBranchId) {
            return [
                'action' => 'error',
                'message' => "Línea {$line}: sucursal_id {$row['branch_id']} no coincide con --branch-id={$expectedBranchId}.",
            ];
        }

        if ($row['barcode'] === '') {
            return [
                'action' => 'error',
                'message' => "Línea {$line}: codigo_barras vacío.",
            ];
        }

        $expiration = LotExpirationMonthYear::normalize($row['expiration']);
        if ($expiration === null || ! LotExpirationMonthYear::isValidFormat($expiration)) {
            return [
                'action' => 'error',
                'message' => "Línea {$line}: vencimiento inválido «{$row['expiration']}» (use mm/YYYY).",
            ];
        }

        $product = $this->findProductByBarcode($row['barcode']);
        if (! $product instanceof Product) {
            return [
                'action' => 'error',
                'message' => "Línea {$line}: producto no encontrado para código «{$row['barcode']}».",
            ];
        }

        $inventory = Inventory::query()
            ->where('branch_id', $expectedBranchId)
            ->where('product_id', $product->id)
            ->first();

        $inventoryQty = $inventory instanceof Inventory
            ? round((float) $inventory->quantity, 3)
            : 0.0;

        $targetQty = $row['quantity'] ?? $inventoryQty;
        $targetQty = round(max(0.0, $targetQty), 3);

        if ($targetQty <= 0.0001) {
            return [
                'action' => 'skip',
                'message' => "Línea {$line}: «{$product->name}» sin existencia que asignar (0).",
            ];
        }

        $existingLotTotal = (float) InventoryLotBalance::query()
            ->where('branch_id', $expectedBranchId)
            ->where('product_id', $product->id)
            ->where('quantity_remaining', '>', 0)
            ->sum('quantity_remaining');

        $seedLotExists = $this->seedLotBalanceExists($expectedBranchId, $product->id);

        if ($existingLotTotal > 0.0001 && $skipWithExistingLots && ! $force && ! $seedLotExists) {
            return [
                'action' => 'skip',
                'message' => "Línea {$line}: «{$product->name}» ya tiene saldo por lote ("
                    .InventoryQuantityFormat::display($existingLotTotal).'). Use --force para lote de apertura.',
            ];
        }

        if ($seedLotExists && ! $force) {
            return [
                'action' => 'skip',
                'message' => "Línea {$line}: «{$product->name}» ya tiene lote de apertura FEFO. Use --force para actualizar.",
            ];
        }

        if ($row['quantity'] === null && $existingLotTotal > 0.0001 && $force) {
            $targetQty = max(0.0, round($inventoryQty - $existingLotTotal, 3));
            if ($targetQty <= 0.0001) {
                return [
                    'action' => 'skip',
                    'message' => "Línea {$line}: «{$product->name}» inventario ya cubierto por lotes existentes.",
                ];
            }
        }

        return [
            'action' => 'seed',
            'product' => $product,
            'expiration' => $expiration,
            'quantity' => $targetQty,
        ];
    }

    private function persistSeedRow(
        Purchase $purchase,
        int $branchId,
        Product $product,
        string $expiration,
        float $quantity,
        bool $force,
    ): void {
        if (! $product->requires_expiry_on_purchase) {
            $product->forceFill(['requires_expiry_on_purchase' => true])->saveQuietly();
        }

        $item = PurchaseItem::withoutEvents(function () use ($purchase, $product, $expiration): PurchaseItem {
            return PurchaseItem::query()->updateOrCreate(
                [
                    'purchase_id' => $purchase->getKey(),
                    'product_id' => $product->getKey(),
                ],
                [
                    'quantity_ordered' => 0,
                    'quantity_received' => 0,
                    'unit_cost' => round(max(0.0, (float) ($product->cost_price ?? 0)), 2),
                    'line_discount_percent' => 0,
                    'line_vat_percent' => 0,
                    'line_subtotal' => 0,
                    'tax_amount' => 0,
                    'line_total' => 0,
                    'product_name_snapshot' => (string) $product->name,
                    'sku_snapshot' => PosInventoryStockFailureRegistrar::resolveProductCode($product),
                    'lot_expiration_month_year' => $expiration,
                    'notes' => 'Lote FEFO de apertura (CSV). No genera entrada de inventario.',
                ],
            );
        });

        $invoiceRef = (string) ($purchase->supplier_invoice_number ?? $purchase->purchase_number);

        $lot = ProductLot::query()->updateOrCreate(
            ['purchase_item_id' => $item->getKey()],
            [
                'purchase_id' => $purchase->getKey(),
                'product_id' => $product->getKey(),
                'supplier_invoice_number' => $invoiceRef,
                'expiration_month_year' => $expiration,
            ],
        );

        $balance = InventoryLotBalance::query()
            ->where('branch_id', $branchId)
            ->where('product_lot_id', $lot->getKey())
            ->first();

        if ($balance instanceof InventoryLotBalance) {
            if ($force) {
                if ($quantity <= 0.0001) {
                    $balance->delete();
                } else {
                    $balance->forceFill([
                        'product_id' => $product->getKey(),
                        'quantity_remaining' => $quantity,
                    ])->save();
                }
            }

            return;
        }

        if ($quantity <= 0.0001) {
            return;
        }

        InventoryLotBalance::query()->create([
            'branch_id' => $branchId,
            'product_lot_id' => $lot->getKey(),
            'product_id' => $product->getKey(),
            'quantity_remaining' => $quantity,
        ]);
    }

    private function resolveSeedPurchase(int $branchId): Purchase
    {
        $supplierId = (int) config('inventory.fefo_seed_supplier_id', 0);
        if ($supplierId <= 0) {
            $supplierId = (int) Supplier::query()->orderBy('id')->value('id');
        }

        if ($supplierId <= 0) {
            throw new RuntimeException('No hay proveedor configurado. Defina INVENTORY_FEFO_SEED_SUPPLIER_ID en .env.');
        }

        $purchaseNumber = self::SEED_PURCHASE_NUMBER_PREFIX.$branchId;
        $invoiceNumber = 'FEFO-APERTURA-B'.$branchId;

        return Purchase::query()->firstOrCreate(
            ['purchase_number' => $purchaseNumber],
            [
                'supplier_id' => $supplierId,
                'branch_id' => $branchId,
                'status' => PurchaseStatus::Received,
                'received_at' => now(),
                'ordered_at' => now(),
                'subtotal' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'total' => 0,
                'supplier_invoice_number' => $invoiceNumber,
                'payment_status' => 'pagado',
                'notes' => 'Inventario inicial FEFO (CSV). Solo registra lotes; no modifica existencias.',
                'created_by' => 'sistema',
                'updated_by' => 'sistema',
            ],
        );
    }

    private function findProductByBarcode(string $barcode): ?Product
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }

        $product = Product::query()->where('barcode', $barcode)->first();
        if ($product instanceof Product) {
            return $product;
        }

        if (ctype_digit($barcode)) {
            $trimmed = ltrim($barcode, '0');
            if ($trimmed !== '' && $trimmed !== $barcode) {
                $product = Product::query()->where('barcode', $trimmed)->first();
                if ($product instanceof Product) {
                    return $product;
                }
            }
        }

        return null;
    }

    private function seedLotBalanceExists(int $branchId, int $productId): bool
    {
        $purchaseId = Purchase::query()
            ->where('purchase_number', self::SEED_PURCHASE_NUMBER_PREFIX.$branchId)
            ->value('id');

        if ($purchaseId === null) {
            return false;
        }

        return InventoryLotBalance::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->whereIn('product_lot_id', ProductLot::query()
                ->where('purchase_id', $purchaseId)
                ->select('id'))
            ->where('quantity_remaining', '>', 0)
            ->exists();
    }

    private function normalizeExpirationInput(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^(\d{1,2})[\/\-](\d{4})$/', $value, $matches)) {
            return str_pad($matches[1], 2, '0', STR_PAD_LEFT).'/'.$matches[2];
        }

        return $value;
    }

    private function parseQuantity(string $value): float
    {
        $value = trim(str_replace(' ', '', $value));
        $value = str_replace(',', '.', $value);

        return round(max(0.0, (float) $value), 3);
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{processed: int, seeded: int, skipped: int, errors: list<string>, warnings: list<string>}  $stats
     * @param  array{action: string, message?: string}  $outcome
     */
    private function mergeOutcome(array &$stats, array $outcome): void
    {
        if ($outcome['action'] === 'error') {
            $stats['errors'][] = (string) ($outcome['message'] ?? 'Error desconocido.');

            return;
        }

        if ($outcome['action'] === 'skip') {
            $stats['skipped']++;
            if (isset($outcome['message'])) {
                $stats['warnings'][] = (string) $outcome['message'];
            }

            return;
        }
    }
}
