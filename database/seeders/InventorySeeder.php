<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = Branch::query()->where('is_active', true)->get();

        if ($branches->isEmpty()) {
            return;
        }

        $products = Product::query()
            ->whereIn('sku', [
                'MED-PARA-500-001',
                'MED-IBUP-400-002',
                'MED-AMOX-875-003',
                'PER-LOC-HIDRA-004',
                'HIG-GEL-ANTI-005',
                'ALI-ENTERAL-006',
                'ALI-SUPL-ADUL-007',
                'EQU-TENS-08',
                'EQU-GLUCO-09',
                'MED-LOS-50-010',
            ])
            ->get()
            ->keyBy('sku');

        if ($products->isEmpty()) {
            return;
        }

        $stockPlan = [
            'MED-PARA-500-001' => ['quantity' => 160, 'reserved' => 12, 'reorder' => 40, 'min' => 50, 'max' => 280, 'location' => 'Farmacia A1', 'sale_price' => 18500, 'cost_price' => 12900, 'tax_rate' => 0, 'discount_percent' => 0],
            'MED-IBUP-400-002' => ['quantity' => 120, 'reserved' => 9, 'reorder' => 35, 'min' => 40, 'max' => 220, 'location' => 'Farmacia A2', 'sale_price' => 21900, 'cost_price' => 15400, 'tax_rate' => 0, 'discount_percent' => 0],
            'MED-AMOX-875-003' => ['quantity' => 85, 'reserved' => 6, 'reorder' => 25, 'min' => 30, 'max' => 140, 'location' => 'Farmacia A3', 'sale_price' => 46800, 'cost_price' => 33750, 'tax_rate' => 0, 'discount_percent' => 0],
            'PER-LOC-HIDRA-004' => ['quantity' => 60, 'reserved' => 3, 'reorder' => 15, 'min' => 20, 'max' => 120, 'location' => 'Dermo B1', 'sale_price' => 32500, 'cost_price' => 21800, 'tax_rate' => 19, 'discount_percent' => 0],
            'HIG-GEL-ANTI-005' => ['quantity' => 140, 'reserved' => 8, 'reorder' => 30, 'min' => 45, 'max' => 260, 'location' => 'Higiene B2', 'sale_price' => 12900, 'cost_price' => 8200, 'tax_rate' => 19, 'discount_percent' => 0],
            'ALI-ENTERAL-006' => ['quantity' => 55, 'reserved' => 4, 'reorder' => 18, 'min' => 20, 'max' => 90, 'location' => 'Nutricion C1', 'sale_price' => 45800, 'cost_price' => 33600, 'tax_rate' => 5, 'discount_percent' => 0],
            'ALI-SUPL-ADUL-007' => ['quantity' => 70, 'reserved' => 5, 'reorder' => 20, 'min' => 25, 'max' => 120, 'location' => 'Nutricion C2', 'sale_price' => 39200, 'cost_price' => 28100, 'tax_rate' => 5, 'discount_percent' => 0],
            'EQU-TENS-08' => ['quantity' => 18, 'reserved' => 1, 'reorder' => 5, 'min' => 6, 'max' => 30, 'location' => 'Equipos D1', 'sale_price' => 149900, 'cost_price' => 112500, 'tax_rate' => 19, 'discount_percent' => 0],
            'EQU-GLUCO-09' => ['quantity' => 24, 'reserved' => 2, 'reorder' => 7, 'min' => 8, 'max' => 36, 'location' => 'Equipos D2', 'sale_price' => 99500, 'cost_price' => 73100, 'tax_rate' => 19, 'discount_percent' => 0],
            'MED-LOS-50-010' => ['quantity' => 95, 'reserved' => 7, 'reorder' => 30, 'min' => 35, 'max' => 160, 'location' => 'Farmacia A4', 'sale_price' => 17400, 'cost_price' => 11850, 'tax_rate' => 0, 'discount_percent' => 0],
        ];

        foreach ($branches as $branch) {
            foreach ($stockPlan as $sku => $plan) {
                $product = $products->get($sku);

                if (! $product) {
                    continue;
                }

                Inventory::query()->updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'quantity' => $plan['quantity'],
                        'reserved_quantity' => $plan['reserved'],
                        'reorder_point' => $plan['reorder'],
                        'minimum_stock' => $plan['min'],
                        'maximum_stock' => $plan['max'],
                        'storage_location' => $plan['location'].'-'.$branch->code,
                        'allow_negative_stock' => false,
                        'sale_price' => $plan['sale_price'],
                        'cost_price' => $plan['cost_price'],
                        'tax_rate' => $plan['tax_rate'],
                        'discount_percent' => $plan['discount_percent'],
                        'last_movement_at' => now(),
                        'last_stock_take_at' => now(),
                        'notes' => 'Carga inicial de inventario por InventorySeeder.',
                        'created_by' => 'InventorySeeder',
                        'updated_by' => 'InventorySeeder',
                    ],
                );
            }
        }
    }
}
