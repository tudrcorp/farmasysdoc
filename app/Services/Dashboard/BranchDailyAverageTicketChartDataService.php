<?php

namespace App\Services\Dashboard;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Ticket promedio diario por sucursal: total de ventas / clientes que compraron ese día.
 */
final class BranchDailyAverageTicketChartDataService
{
    /**
     * @param  list<int>  $branchIds
     * @return array{
     *     labels: list<string>,
     *     day_keys: list<string>,
     *     branch_names: list<string>,
     *     datasets: list<array{
     *         label: string,
     *         data: list<float>,
     *         daily_totals: list<float>,
     *         customer_counts: list<int>,
     *     }>,
     * }
     */
    public function chartForCurrentMonth(array $branchIds): array
    {
        $monthStart = now()->startOfMonth();
        $daysInMonth = $monthStart->daysInMonth;
        $dayKeys = $this->dayKeys($monthStart);
        $labels = $this->dayLabels($daysInMonth);

        if ($branchIds === []) {
            return [
                'labels' => $labels,
                'day_keys' => $dayKeys,
                'branch_names' => [],
                'datasets' => [],
            ];
        }

        $dateExpression = $this->dateGroupExpression();
        $customerCountExpression = $this->customerCountExpression();

        $rows = Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sold_at', [
                $monthStart->copy()->startOfDay(),
                $monthStart->copy()->endOfMonth()->endOfDay(),
            ])
            ->selectRaw(
                "branch_id, {$dateExpression} as sold_day, SUM(CAST(total AS DECIMAL(14,2))) as total_amount, {$customerCountExpression} as customer_count",
            )
            ->groupBy('branch_id', DB::raw($dateExpression))
            ->get();

        $indexed = [];
        foreach ($rows as $row) {
            $branchId = (int) $row->branch_id;
            $dayKey = (string) $row->sold_day;
            $totalAmount = round((float) $row->total_amount, 2);
            $customerCount = (int) $row->customer_count;
            $indexed[$branchId][$dayKey] = [
                'average' => $customerCount > 0
                    ? round($totalAmount / $customerCount, 2)
                    : 0.0,
                'total_amount' => $totalAmount,
                'customer_count' => $customerCount,
            ];
        }

        $branchNames = Branch::query()
            ->whereIn('id', $branchIds)
            ->pluck('name', 'id');

        $datasets = [];
        foreach (array_values($branchIds) as $branchId) {
            $series = [];
            $dailyTotals = [];
            $customerCounts = [];

            foreach ($dayKeys as $dayKey) {
                $point = $indexed[$branchId][$dayKey] ?? [
                    'average' => 0.0,
                    'total_amount' => 0.0,
                    'customer_count' => 0,
                ];

                $series[] = (float) $point['average'];
                $dailyTotals[] = (float) $point['total_amount'];
                $customerCounts[] = (int) $point['customer_count'];
            }

            $branchName = $branchNames[$branchId] ?? ('Sucursal #'.$branchId);
            $datasets[] = [
                'label' => (string) $branchName,
                'data' => $series,
                'daily_totals' => $dailyTotals,
                'customer_counts' => $customerCounts,
            ];
        }

        return [
            'labels' => $labels,
            'day_keys' => $dayKeys,
            'branch_names' => array_map(
                static fn (int $branchId): string => (string) ($branchNames[$branchId] ?? ('Sucursal #'.$branchId)),
                array_values($branchIds),
            ),
            'datasets' => $datasets,
        ];
    }

    /**
     * @return list<string>
     */
    private function dayLabels(int $daysInMonth): array
    {
        $labels = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $labels[] = str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    private function dayKeys(CarbonInterface $monthStart): array
    {
        $keys = [];
        for ($day = 1; $day <= $monthStart->daysInMonth; $day++) {
            $keys[] = $monthStart->copy()->day($day)->format('Y-m-d');
        }

        return $keys;
    }

    private function dateGroupExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => 'date(sold_at)',
            'pgsql' => 'sold_at::date',
            default => 'DATE(sold_at)',
        };
    }

    private function customerCountExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "COUNT(DISTINCT CASE WHEN client_id IS NOT NULL THEN 'c' || client_id ELSE 's' || id END)",
            'pgsql' => "COUNT(DISTINCT CASE WHEN client_id IS NOT NULL THEN ('c' || client_id::text) ELSE ('s' || id::text) END)",
            default => "COUNT(DISTINCT CASE WHEN client_id IS NOT NULL THEN CONCAT('c', client_id) ELSE CONCAT('s', id) END)",
        };
    }
}
