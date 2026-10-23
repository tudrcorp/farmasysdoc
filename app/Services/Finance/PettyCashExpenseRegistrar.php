<?php

namespace App\Services\Finance;

use App\Enums\PettyCashCurrency;
use App\Enums\PettyCashFundStatus;
use App\Models\PettyCashExpense;
use App\Models\PettyCashFund;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class PettyCashExpenseRegistrar
{
    public function __construct(
        private PettyCashAmountResolver $amountResolver,
    ) {}

    /**
     * @param  array{
     *     spent_at: CarbonInterface|string,
     *     description: string,
     *     currency: PettyCashCurrency|string,
     *     amount: float|int|string,
     *     receipt_path: string
     * }  $payload
     */
    public function register(PettyCashFund $fund, User $actor, array $payload): PettyCashExpense
    {
        if (! $actor->isAdministrator() && ! $actor->isManager()) {
            throw new RuntimeException('No tiene permiso para registrar gastos de caja chica.');
        }

        $description = trim((string) ($payload['description'] ?? ''));
        $receipt = trim((string) ($payload['receipt_path'] ?? ''));
        if ($description === '') {
            throw new InvalidArgumentException('Describa el gasto.');
        }
        if ($receipt === '') {
            throw new InvalidArgumentException('Adjunte la foto de la factura o del ticket.');
        }

        $currency = $payload['currency'] instanceof PettyCashCurrency
            ? $payload['currency']
            : PettyCashCurrency::tryFrom((string) ($payload['currency'] ?? ''));
        if (! $currency instanceof PettyCashCurrency) {
            throw new InvalidArgumentException('Indique si el gasto es en dólares o en bolívares.');
        }

        $spentAt = $payload['spent_at'] instanceof CarbonInterface
            ? $payload['spent_at']
            : Carbon::parse((string) $payload['spent_at']);

        $amount = round((float) $payload['amount'], 2);
        $converted = $this->amountResolver->resolve($currency, $amount, $spentAt);

        return DB::transaction(function () use ($fund, $actor, $description, $receipt, $currency, $spentAt, $amount, $converted): PettyCashExpense {
            $locked = PettyCashFund::query()->whereKey($fund->getKey())->lockForUpdate()->first();
            if (! $locked instanceof PettyCashFund) {
                throw new RuntimeException('No se encontró la caja chica.');
            }

            if (! $locked->canRegisterExpenses()) {
                throw new InvalidArgumentException('Solo puede registrar gastos en una caja chica aprobada con saldo disponible.');
            }

            if ($converted['amount_usd'] - (float) $locked->remaining_usd > 0.009) {
                throw new InvalidArgumentException(
                    'El gasto supera el disponible (US$ '.number_format((float) $locked->remaining_usd, 2, ',', '.').').',
                );
            }

            $expense = $locked->expenses()->create([
                'spent_at' => $spentAt,
                'description' => $description,
                'currency' => $currency,
                'amount' => $amount,
                'amount_usd' => $converted['amount_usd'],
                'amount_ves' => $converted['amount_ves'],
                'bcv_ves_per_usd' => $converted['bcv_ves_per_usd'],
                'receipt_path' => $receipt,
                'registered_by' => $actor->id,
            ]);

            $remainingUsd = round((float) $locked->remaining_usd - $converted['amount_usd'], 2);
            if ($remainingUsd <= 0.009) {
                $locked->forceFill([
                    'remaining_usd' => 0,
                    'remaining_ves' => 0,
                    'status' => PettyCashFundStatus::Depleted,
                ])->save();
            } else {
                $rate = $converted['bcv_ves_per_usd'];
                $remainingVes = ($rate !== null && $rate > 0)
                    ? round($remainingUsd * $rate, 2)
                    : max(0, round((float) $locked->remaining_ves - $converted['amount_ves'], 2));

                $locked->forceFill([
                    'remaining_usd' => $remainingUsd,
                    'remaining_ves' => $remainingVes,
                ])->save();
            }

            return $expense->refresh();
        });
    }
}
