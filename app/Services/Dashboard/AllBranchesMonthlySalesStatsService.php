<?php

namespace App\Services\Dashboard;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\BranchSalesGoal;
use App\Models\Sale;
use App\Models\User;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Filament\DashboardBranchFilter;
use App\Support\Sales\SaleCollectedMoneyAggregator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Totales mensuales de ventas completadas de todas las sucursales registradas.
 * No aplica el filtro de sucursal del dashboard.
 */
final class AllBranchesMonthlySalesStatsService
{
    public function __construct(
        private readonly SaleCollectedMoneyAggregator $collectedMoneyAggregator,
    ) {}

    /**
     * @return array{
     *     month_label: string,
     *     registered_branches_count: int,
     *     scope_description: string,
     *     branches: list<array{
     *         branch_id: int,
     *         branch_name: string,
     *         total_usd: float,
     *         total_ves: float,
     *         ves_converted_usd: float,
     *         general_total_usd: float,
     *         bcv_rate_used: float|null,
     *         goal_usd: float|null,
     *         goal_progress_percent: float|null,
     *         has_goal: bool,
     *     }>,
     * }
     */
    public function forCurrentMonthByBranch(): array
    {
        return $this->forPeriodByBranch(now()->startOfMonth(), now()->endOfDay());
    }

    /**
     * @return array{
     *     month_label: string,
     *     scope_description: string,
     *     total_usd: float,
     *     total_ves: float,
     *     ves_converted_usd: float,
     *     general_total_usd: float,
     *     bcv_rate_used: float|null,
     *     goal_usd: float|null,
     *     goal_progress_percent: float|null,
     *     has_goal: bool,
     * }
     */
    public function forCurrentMonthGlobalSummary(): array
    {
        $from = now()->startOfMonth();
        $to = now()->endOfDay();
        $monthLabel = ucfirst($from->locale('es')->translatedFormat('F Y'));
        $viewer = Auth::user();

        $sales = $this->salesForPeriod($from, $to);
        $collected = $this->collectedMoneyAggregator->collectedTotals($sales);
        $documentTotalUsd = round((float) $sales->sum('total'), 2);

        $goalUsd = BranchSalesGoal::query()
            ->forPeriod((int) $from->year, (int) $from->month)
            ->where('is_global', true)
            ->value('goal_usd');

        $goalUsd = is_numeric($goalUsd) && (float) $goalUsd > 0
            ? round((float) $goalUsd, 2)
            : null;
        $goalProgress = $this->resolveGoalProgressPercent($documentTotalUsd, $goalUsd);

        return [
            'month_label' => $monthLabel,
            'scope_description' => $this->scopeDescriptionForViewer($viewer),
            'total_usd' => $collected['usd'],
            'total_ves' => $collected['ves'],
            'ves_converted_usd' => 0.0,
            'general_total_usd' => $documentTotalUsd,
            'bcv_rate_used' => $this->resolveBcvRateUsed($sales, $to),
            'goal_usd' => $goalUsd,
            'goal_progress_percent' => $goalProgress,
            'has_goal' => $goalUsd !== null && $goalUsd > 0,
        ];
    }

    /**
     * @return array{
     *     month_label: string,
     *     registered_branches_count: int,
     *     scope_description: string,
     *     branches: list<array{
     *         branch_id: int,
     *         branch_name: string,
     *         total_usd: float,
     *         total_ves: float,
     *         ves_converted_usd: float,
     *         general_total_usd: float,
     *         bcv_rate_used: float|null,
     *         goal_usd: float|null,
     *         goal_progress_percent: float|null,
     *         has_goal: bool,
     *     }>,
     * }
     */
    public function forPeriodByBranch(CarbonInterface $from, CarbonInterface $to): array
    {
        $monthLabel = ucfirst($from->locale('es')->translatedFormat('F Y'));
        $viewer = Auth::user();
        $branchIds = $this->resolveBranchIdsForViewer($viewer);

        if ($branchIds === []) {
            return [
                'month_label' => $monthLabel,
                'registered_branches_count' => 0,
                'scope_description' => $this->scopeDescriptionForViewer($viewer),
                'branches' => [],
            ];
        }

        $branches = Branch::query()
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $goalsByBranchId = BranchSalesGoal::query()
            ->forPeriod((int) $from->year, (int) $from->month)
            ->where('is_global', false)
            ->whereIn('branch_id', $branchIds)
            ->pluck('goal_usd', 'branch_id');

        $salesByBranch = $this->baseSalesQuery($from, $to)
            ->whereIn('branch_id', $branchIds)
            ->with(['conciliationCachea', 'posTerminal'])
            ->get()
            ->groupBy('branch_id');

        $branchStats = [];

        foreach ($branches as $branch) {
            /** @var Collection<int, Sale> $branchSales */
            $branchSales = $salesByBranch->get($branch->id, collect());
            $collected = $this->collectedMoneyAggregator->collectedTotals($branchSales);
            $documentTotalUsd = round((float) $branchSales->sum('total'), 2);
            $goalUsd = $goalsByBranchId->has($branch->id)
                ? round((float) $goalsByBranchId->get($branch->id), 2)
                : null;
            $goalProgress = $this->resolveGoalProgressPercent($documentTotalUsd, $goalUsd);

            $branchStats[] = [
                'branch_id' => (int) $branch->id,
                'branch_name' => (string) $branch->name,
                'total_usd' => $collected['usd'],
                'total_ves' => $collected['ves'],
                'ves_converted_usd' => 0.0,
                'general_total_usd' => $documentTotalUsd,
                'bcv_rate_used' => $this->resolveBcvRateUsed($branchSales, $to),
                'goal_usd' => $goalUsd,
                'goal_progress_percent' => $goalProgress,
                'has_goal' => $goalUsd !== null && $goalUsd > 0,
            ];
        }

        return [
            'month_label' => $monthLabel,
            'registered_branches_count' => $branches->count(),
            'scope_description' => $this->scopeDescriptionForViewer($viewer),
            'branches' => $branchStats,
        ];
    }

    /**
     * @return list<int>
     */
    private function resolveBranchIdsForViewer(?User $viewer): array
    {
        if ($viewer instanceof User && $viewer->isAdministrator()) {
            return Branch::query()
                ->orderBy('name')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
        }

        return DashboardBranchFilter::allowedBranchIdsForCurrentUser();
    }

    private function scopeDescriptionForViewer(?User $viewer): string
    {
        if ($viewer instanceof User && $viewer->isAdministrator()) {
            return 'sin filtro de sucursal';
        }

        if ($viewer instanceof User && $viewer->isManager()) {
            return 'sucursales asignadas';
        }

        if ($viewer instanceof User && $viewer->isCoordinator()) {
            return 'sucursal asignada';
        }

        return 'alcance por sucursal';
    }

    /**
     * @return Collection<int, Sale>
     */
    private function salesForPeriod(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->baseSalesQuery($from, $to)
            ->with(['conciliationCachea', 'posTerminal'])
            ->get();
    }

    /**
     * @return Builder<Sale>
     */
    private function baseSalesQuery(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Sale::query()
            ->excludingInternalBranchTransfers()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereNotNull('branch_id')
            ->whereBetween('sold_at', [$from, $to]);
    }

    /**
     * @param  Collection<int, Sale>  $sales
     */
    private function resolveBcvRateUsed(Collection $sales, CarbonInterface $periodEnd): ?float
    {
        $rates = $sales
            ->map(static fn (Sale $sale): float => (float) ($sale->bcv_ves_per_usd ?? 0))
            ->filter(static fn (float $rate): bool => $rate > 0.00001);

        if ($rates->isNotEmpty()) {
            return round((float) $rates->avg(), 2);
        }

        return app(VenezuelaOfficialUsdVesRateClient::class)->rateForDate($periodEnd);
    }

    private function resolveGoalProgressPercent(float $generalTotalUsd, ?float $goalUsd): ?float
    {
        if ($goalUsd === null || $goalUsd <= 0) {
            return null;
        }

        return round(($generalTotalUsd / $goalUsd) * 100, 1);
    }
}
