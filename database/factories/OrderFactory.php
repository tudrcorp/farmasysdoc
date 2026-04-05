<?php

namespace Database\Factories;

use App\Enums\ConvenioType;
use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 30, 800);
        $taxTotal = round($subtotal * 0.19, 2);
        $discount = 0.0;
        $total = $subtotal + $taxTotal - $discount;

        return [
            'order_number' => fake()->unique()->bothify('PED-2026-#####'),
            'client_id' => Client::factory(),
            'branch_id' => Branch::factory(),
            'partner_company_id' => null,
            'is_wholesale' => false,
            'status' => OrderStatus::Pending,
            'convenio_type' => ConvenioType::Particular,
            'convenio_partner_name' => null,
            'convenio_reference' => null,
            'convenio_notes' => null,
            'delivery_recipient_name' => fake()->name(),
            'delivery_phone' => fake()->phoneNumber(),
            'delivery_address' => fake()->streetAddress(),
            'delivery_city' => fake()->city(),
            'delivery_state' => fake()->randomElement(['Cundinamarca', 'Antioquia']),
            'delivery_notes' => fake()->optional()->sentence(),
            'scheduled_delivery_at' => fake()->optional()->dateTimeBetween('now', '+7 days'),
            'dispatched_at' => null,
            'delivered_at' => null,
            'delivery_assignee' => null,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'discount_total' => $discount,
            'total' => $total,
            'notes' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function withInsuranceConvenio(): static
    {
        return $this->state(fn (array $attributes): array => [
            'convenio_type' => ConvenioType::PrivateInsurance,
            'convenio_partner_name' => fake()->randomElement(['SURA', 'AXA Colpatria', 'Allianz']),
            'convenio_reference' => fake()->bothify('AUT-########'),
            'convenio_notes' => fake()->optional()->sentence(),
        ]);
    }
}
