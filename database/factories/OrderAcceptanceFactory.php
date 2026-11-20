<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderAcceptance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderAcceptance>
 */
class OrderAcceptanceFactory extends Factory
{
    protected $model = OrderAcceptance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 10, 400);
        $rate = 40.0;

        return [
            'order_id' => Order::factory(),
            'partner_company_id' => null,
            'accepted_by' => null,
            'accepted_by_name' => fake()->name(),
            'accepted_at' => now(),
            'bcv_ves_per_usd' => $rate,
            'accepted_total_usd' => $total,
            'accepted_total_ves' => round($total * $rate, 2),
            'delivery_note_number' => 'NEP-'.fake()->unique()->numerify('#######'),
        ];
    }
}
