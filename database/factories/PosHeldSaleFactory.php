<?php

namespace Database\Factories;

use App\Enums\PosHeldSaleStatus;
use App\Models\Branch;
use App\Models\PosHeldSale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosHeldSale>
 */
class PosHeldSaleFactory extends Factory
{
    protected $model = PosHeldSale::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'status' => PosHeldSaleStatus::Paused,
            'client_id' => null,
            'label' => 'Mostrador',
            'note' => null,
            'form_data' => ['line_items' => []],
            'item_lines' => [],
            'items_count' => 0,
            'estimated_total_usd' => fake()->randomFloat(2, 1, 200),
            'paused_at' => now(),
        ];
    }

    public function pausedMinutesAgo(int $minutes): static
    {
        return $this->state(fn (): array => [
            'paused_at' => now()->subMinutes($minutes),
        ]);
    }

    public function discarded(): static
    {
        return $this->state(fn (): array => [
            'status' => PosHeldSaleStatus::Discarded,
            'discarded_at' => now(),
            'discard_reason' => 'El cliente no pagó.',
        ]);
    }
}
