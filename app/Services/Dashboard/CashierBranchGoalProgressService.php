<?php

namespace App\Services\Dashboard;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\BranchSalesGoal;
use App\Models\Sale;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Meta del mes de la sucursal del cajero, avance de la sucursal y aporte de sus propias ventas.
 */
final class CashierBranchGoalProgressService
{
    /**
     * @return array{
     *     branch_name: string|null,
     *     month_label: string,
     *     goal_usd: float|null,
     *     branch_total_usd: float,
     *     branch_progress_percent: float|null,
     *     cashier_total_usd: float,
     *     cashier_operations_count: int,
     *     cashier_goal_share_percent: float|null,
     *     cashier_branch_share_percent: float|null,
     *     cashier_today_usd: float,
     *     cashier_today_operations_count: int,
     *     remaining_to_goal_usd: float|null,
     * }
     */
    public function forCashier(User $cashier): array
    {
        $monthStart = now()->startOfMonth();
        $now = now();
        $branch = $this->resolveBranch($cashier);

        $empty = [
            'branch_name' => $branch?->name,
            'month_label' => ucfirst($monthStart->locale('es')->translatedFormat('F Y')),
            'goal_usd' => null,
            'branch_total_usd' => 0.0,
            'branch_progress_percent' => null,
            'cashier_total_usd' => 0.0,
            'cashier_operations_count' => 0,
            'cashier_goal_share_percent' => null,
            'cashier_branch_share_percent' => null,
            'cashier_today_usd' => 0.0,
            'cashier_today_operations_count' => 0,
            'remaining_to_goal_usd' => null,
        ];

        if (! $branch instanceof Branch) {
            return $empty;
        }

        $goal = BranchSalesGoal::query()
            ->forPeriod((int) $monthStart->year, (int) $monthStart->month)
            ->where('is_global', false)
            ->where('branch_id', $branch->id)
            ->value('goal_usd');
        $goalUsd = is_numeric($goal) && (float) $goal > 0 ? round((float) $goal, 2) : null;

        $branchTotalUsd = round((float) $this->salesQuery($branch, $monthStart, $now)->sum('total'), 2);
        $cashierIdentifiers = BranchAuthScope::saleCreatorMatchValuesForUser($cashier);

        $cashierMonth = $this->salesQuery($branch, $monthStart, $now)->whereIn('created_by', $cashierIdentifiers);
        $cashierToday = $this->salesQuery($branch, $now->startOfDay(), $now)->whereIn('created_by', $cashierIdentifiers);
        $cashierTotalUsd = round((float) (clone $cashierMonth)->sum('total'), 2);

        return [
            ...$empty,
            'goal_usd' => $goalUsd,
            'branch_total_usd' => $branchTotalUsd,
            'branch_progress_percent' => $this->percent($branchTotalUsd, $goalUsd),
            'cashier_total_usd' => $cashierTotalUsd,
            'cashier_operations_count' => (clone $cashierMonth)->count(),
            'cashier_goal_share_percent' => $this->percent($cashierTotalUsd, $goalUsd),
            'cashier_branch_share_percent' => $this->percent($cashierTotalUsd, $branchTotalUsd > 0 ? $branchTotalUsd : null),
            'cashier_today_usd' => round((float) (clone $cashierToday)->sum('total'), 2),
            'cashier_today_operations_count' => (clone $cashierToday)->count(),
            'remaining_to_goal_usd' => $goalUsd !== null ? round(max(0.0, $goalUsd - $branchTotalUsd), 2) : null,
        ];
    }

    private function resolveBranch(User $cashier): ?Branch
    {
        $branchId = $cashier->branch_id ?? ($cashier->restrictedBranchIdsForQueries()[0] ?? null);

        return $branchId !== null ? Branch::query()->find($branchId, ['id', 'name']) : null;
    }

    /**
     * @return Builder<Sale>
     */
    private function salesQuery(Branch $branch, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Sale::query()
            ->excludingInternalBranchTransfers()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->where('branch_id', $branch->id)
            ->whereBetween('sold_at', [$from, $to]);
    }

    private function percent(float $value, ?float $total): ?float
    {
        if ($total === null || $total <= 0) {
            return null;
        }

        return round(($value / $total) * 100, 1);
    }
}
