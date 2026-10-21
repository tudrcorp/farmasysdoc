<?php

namespace App\Services\Finance;

use App\Enums\PettyCashCurrency;
use App\Enums\PettyCashFundStatus;
use App\Models\PettyCashFund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class PettyCashFundReplenishmentRegistrar
{
    public function __construct(
        private readonly PettyCashAmountResolver $amountResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(PettyCashFund $sourceFund, User $requester, array $data): PettyCashFund
    {
        if (! $requester->canManagePettyCash()) {
            throw new RuntimeException('No tiene permiso para solicitar reposición de caja chica.');
        }

        return DB::transaction(function () use ($sourceFund, $requester, $data): PettyCashFund {
            $source = PettyCashFund::query()->whereKey($sourceFund->getKey())->lockForUpdate()->first();
            if (! $source instanceof PettyCashFund) {
                throw new RuntimeException('No se encontró la caja chica origen.');
            }

            if ($source->status !== PettyCashFundStatus::Approved) {
                throw new InvalidArgumentException('Solo puede reponer una caja chica aprobada con saldo.');
            }

            $carriedUsd = (float) $source->remaining_usd;
            $carriedVes = (float) $source->remaining_ves;
            if ($carriedUsd <= 0.009) {
                throw new InvalidArgumentException('No hay saldo disponible para trasladar a la reposición.');
            }

            $currency = PettyCashCurrency::tryFrom((string) ($data['requested_currency'] ?? ''))
                ?? PettyCashCurrency::Usd;

            $resolved = $this->amountResolver->resolve(
                $currency,
                (float) ($data['requested_amount'] ?? 0),
                $data['requested_at'] ?? now(),
            );

            $totalUsd = round($resolved['amount_usd'] + $carriedUsd, 2);
            $totalVes = round($resolved['amount_ves'] + $carriedVes, 2);

            $notes = trim((string) ($data['notes'] ?? ''));
            if ($notes === '') {
                $notes = 'Reposición de caja chica #'.$source->getKey().'.';
            }

            $replacement = PettyCashFund::query()->create([
                'branch_id' => $source->branch_id,
                'replenished_from_fund_id' => $source->getKey(),
                'requested_by' => $requester->getKey(),
                'requested_at' => $data['requested_at'] ?? now(),
                'notes' => $notes,
                'requested_currency' => $currency->value,
                'requested_amount' => (float) ($data['requested_amount'] ?? 0),
                'amount_usd' => $totalUsd,
                'amount_ves' => $totalVes,
                'carried_over_usd' => $carriedUsd,
                'carried_over_ves' => $carriedVes,
                'bcv_ves_per_usd' => $resolved['bcv_ves_per_usd'],
                'remaining_usd' => $totalUsd,
                'remaining_ves' => $totalVes,
                'bank_code' => $source->bank_code,
                'bank_account_type' => $source->bank_account_type,
                'bank_account_number' => $source->bank_account_number,
                'bank_account_holder' => $source->bank_account_holder,
                'holder_phone' => $source->holder_phone,
                'holder_national_id' => $source->holder_national_id,
                'status' => PettyCashFundStatus::PendingApproval,
            ]);

            $source->forceFill([
                'status' => PettyCashFundStatus::Replenished,
                'superseded_by_fund_id' => $replacement->getKey(),
                'remaining_usd' => 0,
                'remaining_ves' => 0,
            ])->save();

            return $replacement->refresh();
        });
    }
}
