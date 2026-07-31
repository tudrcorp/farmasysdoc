<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditLine;
use App\Models\InventoryAuditUpdate;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryAuditUpdate>
 */
class InventoryAuditUpdateFactory extends Factory
{
    protected $model = InventoryAuditUpdate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = Branch::factory();
        $product = Product::factory();
        $audit = InventoryAudit::factory()->state(['branch_id' => $branch]);
        $line = InventoryAuditLine::factory()->state([
            'inventory_audit_id' => $audit,
            'branch_id' => $branch,
            'product_id' => $product,
        ]);

        return [
            'inventory_audit_id' => $audit,
            'inventory_audit_line_id' => $line,
            'branch_id' => $branch,
            'product_id' => $product,
            'product_sku' => 'SKU-1',
            'product_barcode' => '750123',
            'product_name' => 'Producto auditoría',
            'branch_name' => 'Sucursal',
            'previous_quantity' => 10,
            'new_quantity' => 8,
            'quantity_delta' => -2,
            'previous_cost_price' => 5.00,
            'new_cost_price' => 5.00,
            'quantity_changed' => true,
            'cost_changed' => false,
            'processed_by' => User::factory(),
            'processed_by_name' => 'Gerente',
            'processed_at' => now(),
        ];
    }
}
