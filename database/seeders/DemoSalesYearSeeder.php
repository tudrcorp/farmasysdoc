<?php

namespace Database\Seeders;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Inserta 200 ventas completadas de demostración: cliente y producto distintos por venta,
 * con sold_at repartido al azar entre el 1 ene y hoy del año en curso.
 */
class DemoSalesYearSeeder extends Seeder
{
    private const DEMO_SALES_COUNT = 200;

    public function run(): void
    {
        $year = (int) now()->year;

        $this->cleanupPreviousDemoSales($year);

        $branch = Branch::query()->first();
        if ($branch === null) {
            $this->command?->error('No hay sucursales. Ejecuta BranchSeeder antes.');

            return;
        }

        $supplier = Supplier::factory()->create([
            'trade_name' => 'Proveedor demo ventas '.$year,
            'legal_name' => 'Proveedor demo ventas '.$year.' S.A.S.',
        ]);

        $clients = Client::factory()
            ->count(self::DEMO_SALES_COUNT)
            ->create([
                'created_by' => 'demo_sales_seeder',
                'updated_by' => 'demo_sales_seeder',
            ]);

        $products = Product::factory()
            ->count(self::DEMO_SALES_COUNT)
            ->create([
                'supplier_id' => $supplier->id,
                'created_by' => 'demo_sales_seeder',
                'updated_by' => 'demo_sales_seeder',
            ]);

        $periodStart = Carbon::create($year, 1, 1)->startOfDay();
        $periodEnd = now()->copy()->endOfDay();
        if ($periodEnd->lt($periodStart)) {
            $this->command?->error('Fecha actual anterior al inicio del año; no se crearon ventas.');

            return;
        }

        $minTs = $periodStart->getTimestamp();
        $maxTs = $periodEnd->getTimestamp();

        DB::transaction(function () use ($branch, $clients, $products, $year, $minTs, $maxTs): void {
            foreach (range(0, self::DEMO_SALES_COUNT - 1) as $i) {
                /** @var Client $client */
                $client = $clients[$i];
                /** @var Product $product */
                $product = $products[$i];

                $soldAt = Carbon::createFromTimestamp(random_int($minTs, $maxTs));

                $quantity = (float) fake()->randomFloat(3, 1, 8);
                $listPrice = (float) fake()->randomFloat(2, 5, 250);
                $unitCost = round($listPrice * 0.65, 4);

                $profitPercent = $unitCost > 0.00001
                    ? round((($listPrice / $unitCost) - 1) * 100, 4)
                    : 0.0;

                $category = ProductCategory::query()->updateOrCreate(
                    [
                        'slug' => 'demo-sales-'.$year.'-'.$product->id,
                    ],
                    [
                        'name' => 'Cat. demo ventas '.$year.' #'.$product->id,
                        'description' => null,
                        'image' => null,
                        'is_active' => true,
                        'is_medication' => false,
                        'profit_percentage' => $profitPercent,
                        'created_by' => 'demo_sales_seeder',
                        'updated_by' => 'demo_sales_seeder',
                    ],
                );

                $product->update([
                    'product_category_id' => $category->id,
                    'cost_price' => $unitCost,
                    'discount_percent' => 0,
                ]);
                $product->refresh();

                $inventory = Inventory::query()->updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'quantity' => 999,
                        'reserved_quantity' => 0,
                        'allow_negative_stock' => false,
                        'created_by' => 'demo_sales_seeder',
                        'updated_by' => 'demo_sales_seeder',
                    ],
                );

                $inventory->refresh();

                $unitPrice = (float) $product->sale_price;
                $lineSubtotal = round($quantity * $unitPrice, 2);
                $taxAmount = 0.0;
                $lineTotal = $lineSubtotal;

                $lineCostTotal = round($quantity * $unitCost, 2);
                $grossProfit = round($lineTotal - $lineCostTotal, 2);

                $sale = Sale::query()->create([
                    'sale_number' => sprintf('VTA-%d-DEMO-%06d', $year, $i + 1),
                    'branch_id' => $branch->id,
                    'client_id' => $client->id,
                    'status' => SaleStatus::Completed,
                    'subtotal' => $lineSubtotal,
                    'tax_total' => $taxAmount,
                    'discount_total' => 0,
                    'total' => $lineTotal,
                    'payment_method' => fake()->randomElement(['efectivo_usd', 'efectivo_ves', 'mixto_pago', 'pago_movil']),
                    'payment_usd' => $lineTotal,
                    'payment_ves' => 0,
                    'bcv_ves_per_usd' => null,
                    'reference' => null,
                    'payment_status' => 'paid',
                    'notes' => null,
                    'sold_at' => $soldAt,
                    'created_by' => null,
                    'updated_by' => null,
                ]);

                SaleItem::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'inventory_id' => $inventory->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'discount_amount' => 0,
                    'line_subtotal' => $lineSubtotal,
                    'tax_amount' => $taxAmount,
                    'line_total' => $lineTotal,
                    'line_cost_total' => $lineCostTotal,
                    'gross_profit' => $grossProfit,
                    'product_name_snapshot' => $product->name,
                    'sku_snapshot' => $product->sku,
                ]);
            }
        });

        $this->command?->info(sprintf(
            'DemoSalesYearSeeder: %d ventas (%d clientes y %d productos nuevos) en %d.',
            self::DEMO_SALES_COUNT,
            self::DEMO_SALES_COUNT,
            self::DEMO_SALES_COUNT,
            $year
        ));
    }

    private function cleanupPreviousDemoSales(int $year): void
    {
        $saleIds = Sale::query()
            ->where('sale_number', 'like', sprintf('VTA-%d-DEMO-%%', $year))
            ->pluck('id');

        if ($saleIds->isNotEmpty()) {
            SaleItem::query()->whereIn('sale_id', $saleIds)->delete();
            Sale::query()->whereIn('id', $saleIds)->delete();
        }

        $demoSupplierIds = Supplier::query()
            ->where('trade_name', 'Proveedor demo ventas '.$year)
            ->pluck('id');

        if ($demoSupplierIds->isNotEmpty()) {
            $demoProductIds = Product::query()
                ->whereIn('supplier_id', $demoSupplierIds)
                ->pluck('id');

            if ($demoProductIds->isNotEmpty()) {
                $demoInventoryIds = Inventory::query()
                    ->whereIn('product_id', $demoProductIds)
                    ->pluck('id');

                InventoryMovement::query()
                    ->where(function ($q) use ($demoProductIds, $demoInventoryIds): void {
                        $q->whereIn('product_id', $demoProductIds);
                        if ($demoInventoryIds->isNotEmpty()) {
                            $q->orWhereIn('inventory_id', $demoInventoryIds);
                        }
                    })
                    ->delete();

                Inventory::query()->whereIn('product_id', $demoProductIds)->delete();
                Product::query()->whereIn('id', $demoProductIds)->delete();
            }

            Supplier::query()->whereIn('id', $demoSupplierIds)->delete();
        }

        Client::query()->where('created_by', 'demo_sales_seeder')->delete();
    }
}
