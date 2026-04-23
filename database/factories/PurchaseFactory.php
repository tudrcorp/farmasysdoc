<?php

namespace Database\Factories;

use App\Enums\PurchaseEntryCurrency;
use App\Enums\PurchaseStatus;
use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\Finance\DefaultVatRate;
use App\Support\Purchases\PurchasePaymentStatus;
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
        $vatRate = DefaultVatRate::percent() / 100;
        $taxTotal = round($subtotal * $vatRate, 2);
        $discount = 0.0;
        $total = round($subtotal + $taxTotal, 2);
        $today = now()->toDateString();

        return [
            'purchase_number' => fake()->unique()->bothify('OC-2026-#####'),
            'supplier_id' => Supplier::factory(),
            'branch_id' => Branch::factory(),
            'entry_currency' => PurchaseEntryCurrency::USD,
            'official_usd_ves_rate' => null,
            'status' => PurchaseStatus::Draft,
            'ordered_at' => null,
            'expected_delivery_at' => fake()->optional()->dateTimeBetween('+3 days', '+30 days'),
            'received_at' => null,
            'subtotal' => $subtotal,
            'subtotal_exempt_amount' => 0.0,
            'subtotal_taxable_amount' => $subtotal,
            'tax_total' => $taxTotal,
            'discount_total' => $discount,
            'document_discount_percent' => 0.0,
            'document_discount_amount' => 0.0,
            'net_exempt_after_document_discount' => 0.0,
            'net_taxable_after_document_discount' => $subtotal,
            'total' => $total,
            'declared_invoice_total' => $total,
            'supplier_invoice_number' => null,
            'supplier_control_number' => null,
            'supplier_invoice_date' => $today,
            'registered_in_system_date' => $today,
            'payment_status' => PurchasePaymentStatus::PAGADO_CONTADO,
            'notes' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
