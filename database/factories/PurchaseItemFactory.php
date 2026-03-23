<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    protected $model = PurchaseItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->randomFloat(3, 5, 100);
        $unitCost = fake()->randomFloat(4, 1, 150);
        $taxRate = 19.0;
        $lineSubtotal = round($qty * $unitCost, 2);
        $taxAmount = round($lineSubtotal * ($taxRate / 100), 2);
        $lineTotal = $lineSubtotal + $taxAmount;

        return [
            'purchase_id' => Purchase::factory(),
            'product_id' => Product::factory(),
            'inventory_id' => null,
            'quantity_ordered' => $qty,
            'quantity_received' => 0,
            'unit_cost' => $unitCost,
            'tax_rate' => $taxRate,
            'line_subtotal' => $lineSubtotal,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
            'product_name_snapshot' => fake()->words(4, true),
            'sku_snapshot' => fake()->bothify('SKU-####-???'),
            'notes' => null,
        ];
    }
}
