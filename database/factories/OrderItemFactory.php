<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 8);
        $unitPrice = fake()->randomFloat(2, 5, 150);
        $discount = 0.0;
        $taxRate = 19.0;
        $lineSubtotal = round(($quantity * $unitPrice) - $discount, 2);
        $taxAmount = round($lineSubtotal * ($taxRate / 100), 2);
        $lineTotal = $lineSubtotal + $taxAmount;

        return [
            'order_id' => Order::factory(),
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
