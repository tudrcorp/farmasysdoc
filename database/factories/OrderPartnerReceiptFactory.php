<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderPartnerReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderPartnerReceipt>
 */
class OrderPartnerReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'partner_company_id' => null,
            'external_number' => fake()->bothify('IC-####-################'),
            'external_status' => 'registrado-inventario',
            'external_status_label' => 'Registrado en inventario',
            'service_order_number' => null,
            'delivery_note_number' => null,
            'receipt_number' => 'NRA-'.fake()->numerify('######'),
            'received_at' => now(),
            'received_by' => fake()->name(),
            'location' => fake()->city(),
            'is_complete' => true,
            'observations' => null,
            'medications' => [],
            'pdf_path' => null,
            'external_updated_at' => now(),
        ];
    }
}
