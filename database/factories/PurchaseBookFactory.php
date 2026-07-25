<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\PurchaseBook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseBook>
 */
class PurchaseBookFactory extends Factory
{
    protected $model = PurchaseBook::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $period = now()->format('Y/m');

        return [
            'purchase_id' => Purchase::factory(),
            'voucher_number' => fake()->unique()->numberBetween(20260700000058, 20260700099999),
            'retention_agent_name' => config('fiscal.retention_agent.name'),
            'retention_agent_rif' => config('fiscal.retention_agent.rif'),
            'tax_period' => $period,
            'retention_agent_address' => config('fiscal.retention_agent.address'),
            'issue_date' => null,
            'supplier_name' => fake()->company(),
            'supplier_rif' => 'J-'.fake()->numerify('########').'-'.fake()->numerify('#'),
            'supplier_address' => fake()->optional()->streetAddress(),
            'operation_number' => fake()->numberBetween(1, 999),
            'invoice_date' => fake()->date(),
            'invoice_number' => fake()->bothify('FAC-####'),
            'invoice_control_number' => fake()->optional()->bothify('CTRL-####'),
            'operation_class' => fake()->numberBetween(1, 999),
            'affected_control_number' => null,
            'invoice_total_ves' => fake()->randomFloat(2, 100, 100000),
            'purchases_without_vat_credit' => null,
            'taxable_base_ves' => fake()->randomFloat(2, 100, 80000),
            'vat_rate_percent' => 16,
            'tax_caused_ves' => fake()->randomFloat(2, 10, 12800),
            'tax_retained_ves' => fake()->randomFloat(2, 0, 10000),
            'bcv_rate_at_invoice' => fake()->randomFloat(4, 30, 50),
            'seniat_retention_percent' => fake()->randomElement([0, 75, 100]),
            'created_by' => 'sistema',
        ];
    }
}
