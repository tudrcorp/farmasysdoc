<?php

namespace App\Services\Finance;

use App\Enums\PettyCashCurrency;
use App\Services\Hr\HrBcvRateResolver;
use App\Support\Finance\PettyCashMoney;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class PettyCashAmountResolver
{
    public function __construct(
        private HrBcvRateResolver $rateResolver,
    ) {}

    /**
     * @return array{amount_usd: float, amount_ves: float, bcv_ves_per_usd: ?float}
     */
    public function resolve(PettyCashCurrency $currency, float $amount, CarbonInterface|string $date): array
    {
        $target = $date instanceof CarbonInterface
            ? $date
            : Carbon::parse((string) $date);

        $rate = $this->rateResolver->resolveForDate($target);
        $split = PettyCashMoney::split($currency, $amount, $rate);

        return [
            'amount_usd' => $split['amount_usd'],
            'amount_ves' => $split['amount_ves'],
            'bcv_ves_per_usd' => $rate,
        ];
    }
}
