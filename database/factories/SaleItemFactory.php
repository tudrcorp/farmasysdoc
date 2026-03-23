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
        $taxRate = 19.0;
        $lineSubtotal = round(($quantity * $unitPrice) - $discount, 2);
        $taxAmount = round($lineSubtotal * ($taxRate / 100), 2);
        $lineTotal = $lineSubtotal + $taxAmount;

        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'inventory_id' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discount,
            'tax_rate' => $taxRate,
            'line_subtotal' => $lineSubtotal,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
            'product_name_snapshot' => fake()->words(4, true),
            'sku_snapshot' => fake()->bothify('SKU-####-???'),
        ];
    }
}
