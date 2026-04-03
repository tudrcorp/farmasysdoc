<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 10);
        $unitPrice = fake()->randomFloat(2, 5, 200);
        $discount = 0.0;
        $unitCost = fake()->randomFloat(4, 1, (float) $unitPrice);
        $lineSubtotal = round(($quantity * $unitPrice) - $discount, 2);
        $taxAmount = 0.0;
        $lineTotal = $lineSubtotal;
        $lineCostTotal = round($quantity * $unitCost, 2);
        $grossProfit = round($lineTotal - $lineCostTotal, 2);

        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'inventory_id' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_cost' => $unitCost,
            'discount_amount' => $discount,
            'line_subtotal' => $lineSubtotal,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
            'line_cost_total' => $lineCostTotal,
            'gross_profit' => $grossProfit,
            'product_name_snapshot' => fake()->words(4, true),
            'sku_snapshot' => fake()->bothify('SKU-####-???'),
        ];
    }
}
