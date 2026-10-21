<?php

namespace Database\Factories;

use App\Enums\EmployeeBankAccountType;
use App\Enums\PettyCashCurrency;
use App\Enums\PettyCashFundStatus;
use App\Enums\VenezuelanPagoMovilBank;
use App\Models\Branch;
use App\Models\PettyCashFund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PettyCashFund>
 */
class PettyCashFundFactory extends Factory
{
    protected $model = PettyCashFund::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amountUsd = fake()->randomFloat(2, 50, 400);
        $rate = 36.5;
        $amountVes = round($amountUsd * $rate, 2);

        return [
            'branch_id' => Branch::factory(),
            'requested_by' => User::factory(),
            'requested_at' => now()->toDateString(),
            'notes' => fake()->sentence(8),
            'requested_currency' => PettyCashCurrency::Usd,
            'requested_amount' => $amountUsd,
            'amount_usd' => $amountUsd,
            'amount_ves' => $amountVes,
            'carried_over_usd' => 0,
            'carried_over_ves' => 0,
            'bcv_ves_per_usd' => $rate,
            'remaining_usd' => $amountUsd,
            'remaining_ves' => $amountVes,
            'bank_code' => VenezuelanPagoMovilBank::BancoDeVenezuela->value,
            'bank_account_type' => EmployeeBankAccountType::Checking,
            'bank_account_number' => fake()->numerify('0102################'),
            'bank_account_holder' => fake()->name(),
            'holder_phone' => '0412'.fake()->numerify('#######'),
            'holder_national_id' => 'V-'.fake()->numerify('########'),
            'status' => PettyCashFundStatus::PendingApproval,
            'approved_by' => null,
            'approved_at' => null,
            'payment_proof_path' => null,
            'payment_reference' => null,
            'rejection_reason' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PettyCashFundStatus::Approved,
            'approved_by' => User::factory(),
            'approved_at' => now(),
            'payment_proof_path' => 'petty-cash/payment-proofs/comprobante.jpg',
            'payment_reference' => fake()->numerify('REF########'),
        ]);
    }

    public function depleted(): static
    {
        return $this->approved()->state(fn (array $attributes): array => [
            'status' => PettyCashFundStatus::Depleted,
            'remaining_usd' => 0,
            'remaining_ves' => 0,
        ]);
    }
}
