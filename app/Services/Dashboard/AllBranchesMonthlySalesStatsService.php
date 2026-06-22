<?php

namespace App\Services\Dashboard;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\BranchSalesGoal;
use App\Models\Sale;
use App\Models\User;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Filament\DashboardBranchFilter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Totales mensuales de ventas completadas de todas las sucursales registradas.
 * No aplica el filtro de sucursal del dashboard.
 */
final class AllBranchesMonthlySalesStatsService
{
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

        $query = $this->baseSalesQuery($from, $to);

        $totalUsd = round((float) (clone $query)->sum('payment_usd'), 2);
        $totalVes = round((float) (clone $query)->sum('payment_ves'), 2);
        $vesConvertedUsd = round($this->sumVesConvertedToUsd(clone $query), 2);
        $generalTotalUsd = round($totalUsd + $vesConvertedUsd, 2);

        $goalUsd = BranchSalesGoal::query()
            ->forPeriod((int) $from->year, (int) $from->month)
            ->where('is_global', true)
            ->value('goal_usd');

        $goalUsd = is_numeric($goalUsd) && (float) $goalUsd > 0
            ? round((float) $goalUsd, 2)
            : null;
        $goalProgress = $this->resolveGoalProgressPercent($generalTotalUsd, $goalUsd);

        return [
            'month_label' => $monthLabel,
            'scope_description' => $this->scopeDescriptionForViewer($viewer),
            'total_usd' => $totalUsd,
            'total_ves' => $totalVes,
            'ves_converted_usd' => $vesConvertedUsd,
            'general_total_usd' => $generalTotalUsd,
            'bcv_rate_used' => $this->resolveBcvRateUsed($totalVes, $vesConvertedUsd, $to),
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

        $branchStats = [];

        foreach ($branches as $branch) {
            $query = $this->baseSalesQuery($from, $to)
                ->where('branch_id', $branch->id);

            $totalUsd = round((float) (clone $query)->sum('payment_usd'), 2);
            $totalVes = round((float) (clone $query)->sum('payment_ves'), 2);
            $vesConvertedUsd = round($this->sumVesConvertedToUsd(clone $query), 2);
            $generalTotalUsd = round($totalUsd + $vesConvertedUsd, 2);
            $goalUsd = $goalsByBranchId->has($branch->id)
                ? round((float) $goalsByBranchId->get($branch->id), 2)
                : null;
            $goalProgress = $this->resolveGoalProgressPercent($generalTotalUsd, $goalUsd);

            $branchStats[] = [
                'branch_id' => (int) $branch->id,
                'branch_name' => (string) $branch->name,
                'total_usd' => $totalUsd,
                'total_ves' => $totalVes,
                'ves_converted_usd' => $vesConvertedUsd,
                'general_total_usd' => $generalTotalUsd,
                'bcv_rate_used' => $this->resolveBcvRateUsed($totalVes, $vesConvertedUsd, $to),
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

        return 'alcance por sucursal';
    }

    /**
     * @return Builder<Sale>
     */
    private function baseSalesQuery(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereNotNull('branch_id')
            ->whereBetween('sold_at', [$from, $to]);
    }

    /**
     * @param  Builder<Sale>  $query
     */
    private function sumVesConvertedToUsd(Builder $query): float
    {
        $fallbackRate = $this->fallbackBcvRate();
        $rateExpression = $this->bcvRateSqlExpression($fallbackRate);
        $vesCast = match (DB::connection()->getDriverName()) {
            'sqlite' => 'CAST(payment_ves AS REAL)',
            default => 'CAST(payment_ves AS DECIMAL(14,2))',
        };

        $value = $query
            ->selectRaw(
                "SUM(CASE WHEN {$vesCast} > 0 THEN {$vesCast} / ({$rateExpression}) ELSE 0 END) as ves_converted_usd",
            )
            ->value('ves_converted_usd');

        return (float) ($value ?? 0);
    }

    private function bcvRateSqlExpression(float $fallbackRate): string
    {
        $fallback = number_format($fallbackRate, 6, '.', '');

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "COALESCE(NULLIF(CAST(bcv_ves_per_usd AS REAL), 0), {$fallback})",
            default => "COALESCE(NULLIF(CAST(bcv_ves_per_usd AS DECIMAL(14,6)), 0), {$fallback})",
        };
    }

    private function resolveBcvRateUsed(float $totalVes, float $vesConvertedUsd, CarbonInterface $periodEnd): ?float
    {
        if ($totalVes > 0.00001 && $vesConvertedUsd > 0.00001) {
            return round($totalVes / $vesConvertedUsd, 2);
        }

        return app(VenezuelaOfficialUsdVesRateClient::class)->rateForDate($periodEnd);
    }

    private function fallbackBcvRate(): float
    {
        $fallback = config('fiscal.fallback_ves_usd_rate');

        return is_numeric($fallback) && (float) $fallback > 0 ? (float) $fallback : 1.0;
    }

    private function resolveGoalProgressPercent(float $generalTotalUsd, ?float $goalUsd): ?float
    {
        if ($goalUsd === null || $goalUsd <= 0) {
            return null;
        }

        return round(($generalTotalUsd / $goalUsd) * 100, 1);
    }
}
