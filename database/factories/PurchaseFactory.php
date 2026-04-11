<?php

namespace Database\Factories;

use App\Enums\PurchaseStatus;
use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 5000);
        $taxTotal = round($subtotal * 0.19, 2);
        $discount = 0.0;
        $total = $subtotal + $taxTotal - $discount;

        return [
            'purchase_number' => fake()->unique()->bothify('OC-2026-#####'),
            'supplier_id' => Supplier::factory(),
            'branch_id' => Branch::factory(),
            'status' => PurchaseStatus::Draft,
            'ordered_at' => null,
            'expected_delivery_at' => fake()->optional()->dateTimeBetween('+3 days', '+30 days'),
            'received_at' => null,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'discount_total' => $discount,
            'total' => $total,
            'supplier_invoice_number' => null,
            'supplier_control_number' => null,
            'supplier_invoice_date' => null,
            'payment_status' => 'pending',
            'notes' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
