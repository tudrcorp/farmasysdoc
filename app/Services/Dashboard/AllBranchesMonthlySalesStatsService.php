<?php

namespace App\Services\Dashboard;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\BranchSalesGoal;
use App\Models\Sale;
use App\Models\User;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Filament\DashboardBranchFilter;
use App\Support\Filament\DashboardDateRangeFilter;
use App\Support\Sales\SaleCollectedMoneyAggregator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Totales de ventas completadas por período de todas las sucursales registradas.
 * Opcionalmente se acota a la sucursal elegida en el filtro del dashboard.
 * Las metas mensuales se prorratean según los días del período dentro de cada mes.
 */
final class AllBranchesMonthlySalesStatsService
{
    public function __construct(
        private readonly SaleCollectedMoneyAggregator $collectedMoneyAggregator,
    ) {}

    /**
     * @return array{
     *     month_label: string,
     *     is_month_to_date: bool,
     *     includes_today: bool,
     *     goal_is_prorated: bool,
     *     registered_branches_count: int,
     *     scope_description: string,
     *     branches: list<array{
     *         branch_id: int,
     *         branch_name: string,
     *         total_usd: float,
     *         total_ves: float,
     *         ves_converted_usd: float,
     *         general_total_usd: float,
     *         today_usd: float,
     *         today_ves: float,
     *         today_general_total_usd: float,
     *         bcv_rate_used: float|null,
     *         goal_usd: float|null,
     *         goal_progress_percent: float|null,
     *         has_goal: bool,
     *     }>,
     * }
     */
    public function forCurrentMonthByBranch(?int $selectedBranchId = null): array
    {
        return $this->forPeriodByBranch(now()->startOfMonth(), now()->endOfDay(), $selectedBranchId);
    }

    /**
     * @return array<string, mixed>
     *
     * @see self::forPeriodGlobalSummary()
     */
    public function forCurrentMonthGlobalSummary(?int $selectedBranchId = null): array
    {
        return $this->forPeriodGlobalSummary(now()->startOfMonth(), now()->endOfDay(), $selectedBranchId);
    }

    /**
     * Con `$selectedBranchId` los totales son solo de esa sucursal y el progreso mide
     * su aporte a la meta global; además se informa qué porción de la meta global le toca.
     *
     * @return array{
     *     month_label: string,
     *     is_month_to_date: bool,
     *     goal_is_prorated: bool,
     *     scope_description: string,
     *     selected_branch_id: int|null,
     *     selected_branch_name: string|null,
     *     total_usd: float,
     *     total_ves: float,
     *     ves_converted_usd: float,
     *     general_total_usd: float,
     *     bcv_rate_used: float|null,
     *     goal_usd: float|null,
     *     goal_progress_percent: float|null,
     *     has_goal: bool,
     *     branch_goal_usd: float|null,
     *     branch_goal_share_percent: float|null,
     *     branch_goal_progress_percent: float|null,
     * }
     */
    public function forPeriodGlobalSummary(CarbonInterface $from, CarbonInterface $to, ?int $selectedBranchId = null): array
    {
        $monthLabel = $this->periodLabel($from, $to);
        $goalSegments = $this->goalMonthSegments($from, $to);
        $viewer = Auth::user();
        $selectedBranch = $this->resolveSelectedBranch($viewer, $selectedBranchId);

        $sales = $this->salesForPeriod($from, $to, $selectedBranch?->id);
        $collected = $this->collectedMoneyAggregator->collectedTotals($sales);
        $documentTotalUsd = round((float) $sales->sum('total'), 2);

        $goalUsd = $this->proratedGlobalGoal($goalSegments);
        $goalProgress = $this->resolveGoalProgressPercent($documentTotalUsd, $goalUsd);

        $branchGoalUsd = null;

        if ($selectedBranch instanceof Branch) {
            $branchGoalUsd = $this->proratedBranchGoals($goalSegments, [(int) $selectedBranch->id])[(int) $selectedBranch->id] ?? null;
        }

        return [
            'month_label' => $monthLabel,
            'is_month_to_date' => $this->isMonthToDate($from, $to),
            'goal_is_prorated' => $this->goalIsProrated($goalSegments),
            'scope_description' => $selectedBranch instanceof Branch
                ? (string) $selectedBranch->name
                : $this->scopeDescriptionForViewer($viewer),
            'selected_branch_id' => $selectedBranch?->id,
            'selected_branch_name' => $selectedBranch?->name,
            'total_usd' => $collected['usd'],
            'total_ves' => $collected['ves'],
            'ves_converted_usd' => 0.0,
            'general_total_usd' => $documentTotalUsd,
            'bcv_rate_used' => $this->resolveBcvRateUsed($sales, $to),
            'goal_usd' => $goalUsd,
            'goal_progress_percent' => $goalProgress,
            'has_goal' => $goalUsd !== null && $goalUsd > 0,
            'branch_goal_usd' => $branchGoalUsd,
            'branch_goal_share_percent' => $branchGoalUsd !== null
                ? $this->resolveGoalProgressPercent($branchGoalUsd, $goalUsd)
                : null,
            'branch_goal_progress_percent' => $selectedBranch instanceof Branch
                ? $this->resolveGoalProgressPercent($documentTotalUsd, $branchGoalUsd)
                : null,
        ];
    }

    /**
     * Con `$selectedBranchId` (permitida para el usuario) solo se devuelve esa sucursal.
     *
     * @return array{
     *     month_label: string,
     *     is_month_to_date: bool,
     *     includes_today: bool,
     *     goal_is_prorated: bool,
     *     registered_branches_count: int,
     *     scope_description: string,
     *     selected_branch_id: int|null,
     *     branches: list<array{
     *         branch_id: int,
     *         branch_name: string,
     *         total_usd: float,
     *         total_ves: float,
     *         ves_converted_usd: float,
     *         general_total_usd: float,
     *         today_usd: float,
     *         today_ves: float,
     *         today_general_total_usd: float,
     *         bcv_rate_used: float|null,
     *         goal_usd: float|null,
     *         goal_progress_percent: float|null,
     *         has_goal: bool,
     *     }>,
     * }
     */
    public function forPeriodByBranch(CarbonInterface $from, CarbonInterface $to, ?int $selectedBranchId = null): array
    {
        $monthLabel = $this->periodLabel($from, $to);
        $goalSegments = $this->goalMonthSegments($from, $to);
        $isMonthToDate = $this->isMonthToDate($from, $to);
        $includesToday = $to->isSameDay(now());
        $goalIsProrated = $this->goalIsProrated($goalSegments);
        $viewer = Auth::user();
        $branchIds = $this->resolveBranchIdsForViewer($viewer);
        $selectedBranch = $this->resolveSelectedBranch($viewer, $selectedBranchId);
        $scopeDescription = $selectedBranch instanceof Branch
            ? (string) $selectedBranch->name
            : $this->scopeDescriptionForViewer($viewer);

        if ($selectedBranch instanceof Branch) {
            $branchIds = [(int) $selectedBranch->id];
        }

        if ($branchIds === []) {
            return [
                'month_label' => $monthLabel,
                'is_month_to_date' => $isMonthToDate,
                'includes_today' => $includesToday,
                'goal_is_prorated' => $goalIsProrated,
                'registered_branches_count' => 0,
                'scope_description' => $scopeDescription,
                'selected_branch_id' => null,
                'branches' => [],
            ];
        }

        $branches = Branch::query()
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $goalsByBranchId = $this->proratedBranchGoals($goalSegments, $branchIds);

        $salesByBranch = $this->baseSalesQuery($from, $to)
            ->whereIn('branch_id', $branchIds)
            ->with(['conciliationCachea', 'posTerminal'])
            ->get()
            ->groupBy('branch_id');

        $dayStart = now()->startOfDay();
        $branchStats = [];

        foreach ($branches as $branch) {
            /** @var Collection<int, Sale> $branchSales */
            $branchSales = $salesByBranch->get($branch->id, collect());
            $collected = $this->collectedMoneyAggregator->collectedTotals($branchSales);
            $branchSalesToday = $includesToday
                ? $branchSales->filter(
                    static fn (Sale $sale): bool => $sale->sold_at->greaterThanOrEqualTo($dayStart),
                )
                : collect();
            $collectedToday = $this->collectedMoneyAggregator->collectedTotals($branchSalesToday);
            $documentTotalUsd = round((float) $branchSales->sum('total'), 2);
            $goalUsd = $goalsByBranchId[(int) $branch->id] ?? null;
            $goalProgress = $this->resolveGoalProgressPercent($documentTotalUsd, $goalUsd);

            $branchStats[] = [
                'branch_id' => (int) $branch->id,
                'branch_name' => (string) $branch->name,
                'total_usd' => $collected['usd'],
                'total_ves' => $collected['ves'],
                'ves_converted_usd' => 0.0,
                'general_total_usd' => $documentTotalUsd,
                'today_usd' => $collectedToday['usd'],
                'today_ves' => $collectedToday['ves'],
                'today_general_total_usd' => round((float) $branchSalesToday->sum('total'), 2),
                'bcv_rate_used' => $this->resolveBcvRateUsed($branchSales, $to),
                'goal_usd' => $goalUsd,
                'goal_progress_percent' => $goalProgress,
                'has_goal' => $goalUsd !== null && $goalUsd > 0,
            ];
        }

        return [
            'month_label' => $monthLabel,
            'is_month_to_date' => $isMonthToDate,
            'includes_today' => $includesToday,
            'goal_is_prorated' => $goalIsProrated,
            'registered_branches_count' => $branches->count(),
            'scope_description' => $scopeDescription,
            'selected_branch_id' => $selectedBranch?->id,
            'branches' => $branchStats,
        ];
    }

    private function periodLabel(CarbonInterface $from, CarbonInterface $to): string
    {
        if ($this->isMonthToDate($from, $to)) {
            return ucfirst($from->locale('es')->translatedFormat('F Y'));
        }

        return DashboardDateRangeFilter::labelFor($from, $to);
    }

    private function isMonthToDate(CarbonInterface $from, CarbonInterface $to): bool
    {
        $today = now();

        return $from->isSameDay($today->startOfMonth()) && $to->isSameDay($today);
    }

    /**
     * Fracción de cada mes cubierta por el período. El mes en curso desde el día 1 hasta hoy
     * cuenta completo: la meta sigue siendo la del mes aunque aún no termine.
     *
     * @return list<array{year: int, month: int, fraction: float}>
     */
    private function goalMonthSegments(CarbonInterface $from, CarbonInterface $to): array
    {
        $segments = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        $today = now()->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $monthEnd = $cursor->copy()->endOfMonth()->startOfDay();
            $segmentEnd = $monthEnd->lessThan($end) ? $monthEnd : $end;
            $coveredDays = (int) $cursor->diffInDays($segmentEnd) + 1;
            $isRunningMonthToDate = $cursor->day === 1 && $segmentEnd->isSameDay($today);

            $segments[] = [
                'year' => (int) $cursor->year,
                'month' => (int) $cursor->month,
                'fraction' => $isRunningMonthToDate ? 1.0 : min(1.0, $coveredDays / $cursor->daysInMonth),
            ];

            $cursor = $monthEnd->copy()->addDay();
        }

        return $segments;
    }

    /**
     * @param  list<array{year: int, month: int, fraction: float}>  $segments
     */
    private function goalIsProrated(array $segments): bool
    {
        return count($segments) !== 1 || $segments[0]['fraction'] < 1.0;
    }

    /**
     * @param  list<array{year: int, month: int, fraction: float}>  $segments
     */
    private function proratedGlobalGoal(array $segments): ?float
    {
        $total = 0.0;

        foreach ($segments as $segment) {
            $goal = BranchSalesGoal::query()
                ->forPeriod($segment['year'], $segment['month'])
                ->where('is_global', true)
                ->value('goal_usd');

            if (is_numeric($goal) && (float) $goal > 0) {
                $total += (float) $goal * $segment['fraction'];
            }
        }

        return $total > 0 ? round($total, 2) : null;
    }

    /**
     * @param  list<array{year: int, month: int, fraction: float}>  $segments
     * @param  list<int>  $branchIds
     * @return array<int, float>
     */
    private function proratedBranchGoals(array $segments, array $branchIds): array
    {
        $totals = [];

        foreach ($segments as $segment) {
            $goals = BranchSalesGoal::query()
                ->forPeriod($segment['year'], $segment['month'])
                ->where('is_global', false)
                ->whereIn('branch_id', $branchIds)
                ->pluck('goal_usd', 'branch_id');

            foreach ($goals as $branchId => $goal) {
                if (! is_numeric($goal) || (float) $goal <= 0) {
                    continue;
                }

                $totals[(int) $branchId] = ($totals[(int) $branchId] ?? 0.0) + (float) $goal * $segment['fraction'];
            }
        }

        return array_map(static fn (float $goal): float => round($goal, 2), $totals);
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

    private function resolveSelectedBranch(?User $viewer, ?int $selectedBranchId): ?Branch
    {
        if ($selectedBranchId === null || ! in_array($selectedBranchId, $this->resolveBranchIdsForViewer($viewer), true)) {
            return null;
        }

        return Branch::query()->find($selectedBranchId, ['id', 'name']);
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
    private function salesForPeriod(CarbonInterface $from, CarbonInterface $to, ?int $branchId = null): Collection
    {
        return $this->baseSalesQuery($from, $to)
            ->when($branchId !== null, static fn (Builder $query): Builder => $query->where('branch_id', $branchId))
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
