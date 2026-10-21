<?php

namespace App\Services\Finance;

use App\Enums\PettyCashFundStatus;
use App\Models\PettyCashFund;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class PettyCashApprovedDisbursementTotals
{
    /**
     * @param  Builder<PettyCashFund>  $query
     * @return Builder<PettyCashFund>
     */
    private function applyApprovedInPeriodScope(Builder $query, CarbonInterface $from, CarbonInterface $until): Builder
    {
        return $query
            ->whereNotNull('approved_at')
            ->whereBetween('approved_at', [$from, $until])
            ->whereNotIn('status', [
                PettyCashFundStatus::PendingApproval->value,
                PettyCashFundStatus::Rejected->value,
                PettyCashFundStatus::Cancelled->value,
            ]);
    }

    /**
     * Monto desembolsado al aprobar fondos en el período (no gastos registrados después).
     *
     * @return array{count: int, usd: float, ves: float}
     */
    public function forUserAndPeriod(
        User $user,
        CarbonInterface $from,
        CarbonInterface $until,
        ?int $branchId = null,
    ): array {
        $query = $this->applyApprovedInPeriodScope(PettyCashFund::query(), $from, $until);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        } elseif (! $user->isAdministrator() && ! $user->isDeliveryUser()) {
            $branchIds = $user->restrictedBranchIdsForQueries();
            if ($branchIds === []) {
                return ['count' => 0, 'usd' => 0.0, 'ves' => 0.0];
            }

            $query->whereIn('branch_id', $branchIds);
        }

        return $this->sumDisbursements($query);
    }

    /**
     * @param  Builder<PettyCashFund>  $fundScopeQuery
     * @return array{count: int, usd: float, ves: float}
     */
    public function forFundScopeAndPeriod(
        Builder $fundScopeQuery,
        CarbonInterface $from,
        CarbonInterface $until,
    ): array {
        $query = $this->applyApprovedInPeriodScope(clone $fundScopeQuery, $from, $until);

        return $this->sumDisbursements($query);
    }

    /**
     * @param  Builder<PettyCashFund>  $query
     * @return array{count: int, usd: float, ves: float}
     */
    private function sumDisbursements(Builder $query): array
    {
        $model = new PettyCashFund;
        $table = $model->getTable();

        $usdExpression = "CASE WHEN {$table}.replenished_from_fund_id IS NOT NULL "
            ."THEN GREATEST(0, ROUND({$table}.amount_usd - {$table}.carried_over_usd, 2)) "
            ."ELSE {$table}.amount_usd END";

        $vesExpression = "CASE WHEN {$table}.replenished_from_fund_id IS NOT NULL "
            ."THEN GREATEST(0, ROUND({$table}.amount_ves - {$table}.carried_over_ves, 2)) "
            ."ELSE {$table}.amount_ves END";

        $aggregate = (clone $query)
            ->selectRaw('COUNT(*) as aggregate_count')
            ->selectRaw("COALESCE(SUM({$usdExpression}), 0) as aggregate_usd")
            ->selectRaw("COALESCE(SUM({$vesExpression}), 0) as aggregate_ves")
            ->first();

        return [
            'count' => (int) ($aggregate->aggregate_count ?? 0),
            'usd' => round((float) ($aggregate->aggregate_usd ?? 0), 2),
            'ves' => round((float) ($aggregate->aggregate_ves ?? 0), 2),
        ];
    }
}
