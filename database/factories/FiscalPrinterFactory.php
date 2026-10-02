<?php

namespace Database\Factories;

use App\Enums\FiscalPrinterMode;
use App\Enums\FiscalPrinterModel;
use App\Models\Branch;
use App\Models\FiscalPrinter;
use App\Support\Fiscal\FiscalPaymentCodes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalPrinter>
 */
class FiscalPrinterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'physical_cash_box_id' => null,
            'name' => 'Caja '.fake()->unique()->numberBetween(1, 999),
            'model' => FiscalPrinterModel::AclasPp9Plus,
            'serial_number' => fake()->unique()->numerify('31000#####'),
            'fiscal_registry' => fake()->unique()->numerify('ZZP00#####-I'),
            'connection_port' => 'COM3',
            'agent_token_hash' => null,
            'is_active' => true,
            'mode' => FiscalPrinterMode::Disabled,
            'payment_slots' => null,
        ];
    }

    public function withAgentToken(string $plainToken): static
    {
        return $this->state(fn (array $attributes): array => [
            'agent_token_hash' => FiscalPrinter::hashToken($plainToken),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'mode' => FiscalPrinterMode::Active,
            'payment_slots' => array_fill_keys(FiscalPaymentCodes::codes(), '01'),
        ]);
    }

    public function simulation(): static
    {
        return $this->state(fn (array $attributes): array => [
            'mode' => FiscalPrinterMode::Simulation,
            'payment_slots' => array_fill_keys(FiscalPaymentCodes::codes(), '01'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
