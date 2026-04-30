<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

#[Signature('app:import-crossed-products-inventory
    {file : Ruta absoluta del CSV}
    {--branch-id= : ID de la sucursal destino (prioritario; recomendado con varias sucursales)}
    {--branch=La California : Nombre (o parte) de la sucursal si no usas --branch-id}
    {--truncate : Trunca products e inventories antes de importar (destructivo; no usar en multi-sucursal)}')]
#[Description('Importa CSV a inventarios por sucursal: upsert stock; productos/catálogo solo si faltan (sin truncar por defecto)')]
class ImportCrossedProductsInventory extends Command
{
    /**
     * @var array<string, int>
     */
    private array $categoryIdByNormalizedName = [];

    /**
     * @var array<string, string>
     */
    private array $categoryAliases = [
        'medicamentos' => 'medicamentos',
        'miscelaneos' => 'miscelaneos',
        'confiteria-alimentos' => 'confiteria alimentos',
        'confiteria alimentos' => 'confiteria alimentos',
        'medico quirurgico' => 'medico quirurgico',
        'importado' => 'importado',
        'psicotropicos' => 'psocotropicos',
        'psocotropicos' => 'psocotropicos',
        'bebe' => 'bebe',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $branchIdOption = $this->option('branch-id');
        $branchTerm = (string) $this->option('branch');
        $truncate = (bool) $this->option('truncate');

        if (! is_file($file) || ! is_readable($file)) {
            $this->error('No se puede leer el CSV: '.$file);

            return self::FAILURE;
        }

        $branch = null;
        if ($branchIdOption !== null && $branchIdOption !== '' && is_numeric($branchIdOption)) {
            $branch = Branch::query()->whereKey((int) $branchIdOption)->first();
            if (! $branch instanceof Branch) {
                $this->error('No existe sucursal con ID: '.$branchIdOption);

                return self::FAILURE;
            }
        } else {
            $branch = Branch::query()
                ->where('name', 'like', '%'.$branchTerm.'%')
                ->orderBy('name')
                ->first();

            if (! $branch instanceof Branch) {
                $this->error('No encontré una sucursal que coincida con: '.$branchTerm.'. Usa --branch-id= para fijar la sucursal.');

                return self::FAILURE;
            }
        }

        $this->warmCategoryMap();

        $actor = auth()->user()?->email
            ?? auth()->user()?->name
            ?? 'import-csv';

        $supplier = $this->resolveImportSupplier($actor);

        $createdProducts = 0;
        $updatedProducts = 0;
        $upsertedInventories = 0;
        $skippedRows = 0;

        /** @var array<string, bool> $usedBarcodes */
        $usedBarcodes = [];
        /** @var array<string, bool> $usedSkus */
        $usedSkus = [];
        /** @var array<string, bool> $usedSlugs */
        $usedSlugs = [];

        if ($truncate) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            Inventory::query()->truncate();
            Product::query()->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $handle = fopen($file, 'r');
        if (! is_resource($handle)) {
            throw new RuntimeException('No se pudo abrir el CSV para importación.');
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);
            throw new RuntimeException('El CSV no tiene encabezado válido.');
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (! is_array($row) || count($row) < 14) {
                $skippedRows++;

                continue;
            }

            $barcodeRaw = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));
            $departmentRaw = trim((string) ($row[2] ?? ''));
            $stock = $this->csvDecimal($row[3] ?? '0');
            $cost = $this->csvDecimal($row[4] ?? '0');
            $ivaCosto = $this->csvDecimal($row[5] ?? '0');
            $costPlusVat = $this->csvDecimal($row[6] ?? '0');
            $priceWithoutVat = $this->csvDecimal($row[7] ?? '0');
            $ivaFinalPrice = $this->csvDecimal($row[8] ?? '0');
            $finalPriceWithVat = $this->csvDecimal($row[9] ?? '0');
            $percentUtil = $this->parsePercentUtil($row[10] ?? '');
            $reference = trim((string) ($row[11] ?? ''));
            $brand = trim((string) ($row[12] ?? ''));
            $model = trim((string) ($row[13] ?? ''));
            $activeIngredient = $reference !== '' ? [$reference] : null;

            if ($name === '') {
                $skippedRows++;

                continue;
            }

            $category = $this->resolveProductCategoryForDepartment($departmentRaw, $percentUtil, $actor);

            $barcode = $this->sanitizeBarcode($barcodeRaw);
            if ($barcode !== null && isset($usedBarcodes[$barcode])) {
                $barcode = null;
            }

            $existing = null;
            if ($barcode !== null) {
                $existing = Product::query()->where('barcode', $barcode)->first();
            }

            if ($existing instanceof Product) {
                Product::withoutEvents(function () use (
                    $existing,
                    $category,
                    $name,
                    $brand,
                    $model,
                    $priceWithoutVat,
                    $cost,
                    $ivaCosto,
                    $activeIngredient,
                    $actor,
                ): void {
                    $existing->update([
                        'product_category_id' => $category->id,
                        'name' => $name,
                        'brand' => $brand !== '' ? $brand : $existing->brand,
                        'model' => $model !== '' ? $model : $existing->model,
                        'sale_price' => round(max(0.0, $priceWithoutVat), 2),
                        'cost_price' => round(max(0.0, $cost), 2),
                        'discount_percent' => 0,
                        'applies_vat' => $ivaCosto > 0.000001,
                        'active_ingredient' => $activeIngredient ?? $existing->active_ingredient,
                        'updated_by' => $actor,
                    ]);
                });
                $updatedProducts++;
                $product = $existing->fresh();
            } else {
                $sku = $this->makeUniqueSku($barcodeRaw !== '' ? $barcodeRaw : $name, $usedSkus);
                $slug = $this->makeUniqueSlug($name, $barcodeRaw, $usedSlugs);

                $product = Product::withoutEvents(function () use (
                    $supplier,
                    $sku,
                    $barcode,
                    $name,
                    $slug,
                    $brand,
                    $model,
                    $priceWithoutVat,
                    $cost,
                    $ivaCosto,
                    $activeIngredient,
                    $actor,
                    $category,
                ): Product {
                    /** @var Product $created */
                    $created = Product::query()->create([
                        'supplier_id' => $supplier->id,
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'name' => $name,
                        'slug' => $slug,
                        'description' => null,
                        'brand' => $brand !== '' ? $brand : 'Sin marca',
                        'model' => $model !== '' ? $model : null,
                        'sale_price' => round(max(0.0, $priceWithoutVat), 2),
                        'cost_price' => round(max(0.0, $cost), 2),
                        'discount_percent' => 0,
                        'applies_vat' => $ivaCosto > 0.000001,
                        'requires_expiry_on_purchase' => true,
                        'is_active' => true,
                        'created_by' => $actor,
                        'updated_by' => $actor,
                        'product_category_id' => $category->id,
                        'active_ingredient' => $activeIngredient,
                        'concentration' => null,
                        'presentation_type' => null,
                        'requires_prescription' => false,
                        'is_controlled_substance' => false,
                        'ingredients' => null,
                        'allergens' => null,
                        'nutritional_information' => null,
                        'manufacturer' => null,
                        'warranty_months' => null,
                        'medical_device_class' => null,
                        'requires_calibration' => false,
                        'storage_conditions' => null,
                        'expiration_date' => null,
                    ]);

                    return $created;
                });

                $createdProducts++;

                if ($barcode !== null) {
                    $usedBarcodes[$barcode] = true;
                }
            }

            Inventory::withoutEvents(function () use (
                $branch,
                $product,
                $stock,
                $cost,
                $ivaCosto,
                $costPlusVat,
                $priceWithoutVat,
                $ivaFinalPrice,
                $finalPriceWithVat,
                $activeIngredient,
                $actor,
                $reference,
                $category,
            ): void {
                Inventory::query()->updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'quantity' => round(max(0.0, $stock), 3),
                        'reserved_quantity' => 0,
                        'cost_price' => round(max(0.0, $cost), 8),
                        'vat_cost_amount' => round(max(0.0, $ivaCosto), 8),
                        'cost_plus_vat' => round(max(0.0, $costPlusVat), 8),
                        'final_price_without_vat' => round(max(0.0, $priceWithoutVat), 8),
                        'vat_final_price_amount' => round(max(0.0, $ivaFinalPrice), 8),
                        'final_price_with_vat' => round(max(0.0, $finalPriceWithVat), 8),
                        'product_category_id' => $category->id,
                        'active_ingredient' => $activeIngredient,
                        'allow_negative_stock' => false,
                        'created_by' => $actor,
                        'updated_by' => $actor,
                        'notes' => $reference !== '' ? 'Import CSV · '.$reference : 'Import CSV',
                    ],
                );
            });

            $upsertedInventories++;
        }

        fclose($handle);

        $this->info('Importación completada.');
        $this->line('Sucursal: '.$branch->name.' (ID '.$branch->id.')');
        $this->line('Productos creados: '.$createdProducts);
        $this->line('Productos actualizados (ya existían por código): '.$updatedProducts);
        $this->line('Registros de inventario (insert/actualización): '.$upsertedInventories);
        $this->line('Filas omitidas: '.$skippedRows);

        return self::SUCCESS;
    }

    private function warmCategoryMap(): void
    {
        /** @var EloquentCollection<int, ProductCategory> $categories */
        $categories = ProductCategory::query()
            ->select(['id', 'name'])
            ->get();

        foreach ($categories as $category) {
            $this->categoryIdByNormalizedName[$this->normalizeDepartment((string) $category->name)] = (int) $category->id;
        }
    }

    private function resolveProductCategoryForDepartment(string $departmentRaw, float $profitPercentHint, string $actor): ProductCategory
    {
        $resolvedId = $this->resolveCategoryIdFromDepartment($departmentRaw);
        if ($resolvedId !== null) {
            /** @var ProductCategory $found */
            $found = ProductCategory::query()->findOrFail($resolvedId);

            return $found;
        }

        $normalized = $this->normalizeDepartment($departmentRaw);
        $slugBase = $normalized !== '' ? $normalized : 'sin-departamento';
        $slug = Str::slug($slugBase);
        if ($slug === '') {
            $slug = 'import-'.Str::lower(Str::random(10));
        }

        $displayName = $this->departmentDisplayName($departmentRaw);

        return ProductCategory::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $displayName,
                'description' => null,
                'image' => null,
                'is_active' => true,
                'is_medication' => str_contains($normalized, 'medic'),
                'profit_percentage' => round(max(0.0, min(999.99, $profitPercentHint)), 2),
                'created_by' => $actor,
                'updated_by' => $actor,
            ],
        );
    }

    private function resolveCategoryIdFromDepartment(string $department): ?int
    {
        $normalized = $this->normalizeDepartment($department);
        $candidate = $this->categoryAliases[$normalized] ?? $normalized;

        return $this->categoryIdByNormalizedName[$candidate] ?? null;
    }

    private function normalizeDepartment(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/^\d+\s*-\s*/u', '', $normalized) ?? $normalized;
        $normalized = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $normalized,
        );
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function departmentDisplayName(string $raw): string
    {
        $trimmed = trim($raw);
        if (preg_match('/^\d+\s*-\s*(.+)$/u', $trimmed, $matches) === 1) {
            return trim($matches[1]);
        }

        return $trimmed !== '' ? $trimmed : 'Sin departamento';
    }

    private function parsePercentUtil(mixed $raw): float
    {
        $value = trim((string) $raw);
        $value = str_replace(['%', ' '], '', $value);
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? max(0.0, (float) $value) : 0.0;
    }

    private function csvDecimal(mixed $raw): float
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return 0.0;
        }

        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function sanitizeBarcode(string $barcode): ?string
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }

        return Str::limit($barcode, 255, '');
    }

    /**
     * @param  array<string, bool>  $usedSkus
     */
    private function makeUniqueSku(string $seed, array &$usedSkus): string
    {
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $seed) ?? '');
        if ($base === '') {
            $base = strtoupper(Str::random(8));
        }

        $base = Str::limit($base, 36, '');
        $candidate = 'CSV-'.$base;
        $suffix = 1;

        while (isset($usedSkus[$candidate]) || Product::query()->where('sku', $candidate)->exists()) {
            $candidate = 'CSV-'.$base.'-'.$suffix;
            $candidate = Str::limit($candidate, 255, '');
            $suffix++;
        }

        $usedSkus[$candidate] = true;

        return $candidate;
    }

    /**
     * @param  array<string, bool>  $usedSlugs
     */
    private function makeUniqueSlug(string $name, string $barcodeSeed, array &$usedSlugs): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'producto';
        }
        $base = Str::limit($base, 220, '');

        $suffixSeed = $barcodeSeed !== '' ? Str::slug($barcodeSeed) : Str::lower(Str::random(6));
        $candidate = $base.'-'.$suffixSeed;
        $candidate = Str::limit($candidate, 255, '');
        $suffix = 1;

        while (isset($usedSlugs[$candidate]) || Product::query()->where('slug', $candidate)->exists()) {
            $candidate = Str::limit($base.'-'.$suffixSeed.'-'.$suffix, 255, '');
            $suffix++;
        }

        $usedSlugs[$candidate] = true;

        return $candidate;
    }

    private function resolveImportSupplier(string $actor): Supplier
    {
        $supplier = Supplier::query()
            ->where('tax_id', 'CSV-IMPORT')
            ->first();

        if ($supplier instanceof Supplier) {
            return $supplier;
        }

        return Supplier::query()->create([
            'code' => null,
            'legal_name' => 'Proveedor importación CSV',
            'trade_name' => 'Importación CSV',
            'tax_id' => 'CSV-IMPORT',
            'email' => null,
            'phone' => null,
            'mobile_phone' => null,
            'website' => null,
            'address' => null,
            'city' => null,
            'state' => null,
            'country' => 'Venezuela',
            'contact_name' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'payment_terms' => null,
            'notes' => 'Creado automáticamente para importación masiva de productos.',
            'is_active' => true,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);
    }
}
