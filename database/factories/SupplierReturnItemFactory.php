<?php

namespace Database\Factories;

use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierReturnItem>
 */
class SupplierReturnItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_return_id' => SupplierReturn::factory(),
            'barcode' => fake()->ean13(),
            'product_name' => fake()->words(3, true),
            'quantity_invoiced' => 10,
            'quantity_received' => 12,
            'quantity_excess' => 2,
            'sort' => 0,
        ];
    }
}
