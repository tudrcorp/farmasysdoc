<?php

namespace Database\Factories;

use App\Enums\InventoryAuditLineStatus;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditLine;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryAuditLine>
 */
class InventoryAuditLineFactory extends Factory
{
    protected $model = InventoryAuditLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = Branch::factory();
        $product = Product::factory();
        $inventory = Inventory::factory()->state([
            'branch_id' => $branch,
            'product_id' => $product,
            'quantity' => 10,
        ]);

        return [
            'inventory_audit_id' => InventoryAudit::factory(),
            'inventory_id' => $inventory,
            'product_id' => $product,
            'branch_id' => $branch,
            'status' => InventoryAuditLineStatus::Pending,
            'system_quantity' => 10,
            'system_cost_price' => 5.00,
            'counted_quantity' => null,
            'new_cost_price' => null,
            'quantity_delta' => null,
            'cost_changed' => false,
            'inventory_movement_id' => null,
            'processed_by' => null,
            'processed_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => InventoryAuditLineStatus::Pending,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'status' => InventoryAuditLineStatus::Verified,
            'counted_quantity' => 10,
            'quantity_delta' => 0,
            'processed_at' => now(),
        ]);
    }

    public function updated(): static
    {
        return $this->state(fn (): array => [
            'status' => InventoryAuditLineStatus::Updated,
            'counted_quantity' => 8,
            'quantity_delta' => -2,
            'processed_at' => now(),
        ]);
    }
}
