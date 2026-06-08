<?php

namespace App\Services\Dashboard;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Sale;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Sales\SalePaymentMethodLabels;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Totales cobrados por método de pago en un día, desglosados por sucursal.
 */
final class BranchSalesDayPaymentMethodChartDataService
{
    /**
     * @param  list<int>  $branchIds
     * @return array{
     *     labels: list<string>,
     *     branches: list<array{
     *         branch_id: int,
     *         branch_name: string,
     *         chart_values: list<float>,
     *         methods: list<array{
     *             key: string,
     *             label: string,
     *             total_usd: float,
     *             collected_usd: float,
     *             collected_ves: float,
     *             ves_equivalent_for_usd: float|null,
     *             legend_label: string
     *         }>,
     *         branch_total_usd: float
     *     }>,
     *     bcv_rate: float|null,
     *     total_day_usd: float
     * }
     */
    public function chartForDay(array $branchIds, CarbonInterface $day): array
    {
        if ($branchIds === []) {
            return $this->emptyChart();
        }

        $dayStart = $day->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();
        $officialRate = app(VenezuelaOfficialUsdVesRateClient::class)->rateForDate($day);

        $branchNames = Branch::query()
            ->whereIn('id', $branchIds)
            ->pluck('name', 'id');

        $rows = Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sold_at', [$dayStart, $dayEnd])
            ->select('branch_id', 'payment_method')
            ->selectRaw('SUM(CAST(total AS DECIMAL(14,2))) as total_usd')
            ->selectRaw('SUM(COALESCE(payment_usd, 0)) as collected_usd')
            ->selectRaw('SUM(COALESCE(payment_ves, 0)) as collected_ves')
            ->selectRaw('SUM(COALESCE(payment_usd, 0) * COALESCE(NULLIF(bcv_ves_per_usd, 0), 0)) as usd_weighted_ves')
            ->groupBy('branch_id', 'payment_method')
            ->get();

        $indexed = [];
        $methodKeysFound = [];

        foreach ($rows as $row) {
            $branchId = (int) $row->branch_id;
            $key = strtolower(trim((string) ($row->payment_method ?? '')));
            if ($key === '') {
                $key = '__empty';
            }

            $indexed[$branchId][$key] = [
                'total_usd' => round((float) $row->total_usd, 2),
                'collected_usd' => round((float) $row->collected_usd, 2),
                'collected_ves' => round((float) $row->collected_ves, 2),
                'usd_weighted_ves' => round((float) $row->usd_weighted_ves, 2),
            ];

            $methodKeysFound[$key] = true;
        }

        if ($methodKeysFound === []) {
            return $this->emptyChart($officialRate, $branchIds, $branchNames);
        }

        $orderedMethodKeys = $this->orderedMethodKeys(array_keys($methodKeysFound));
        $labels = array_map(
            fn (string $key): string => $key === '__empty' ? 'Sin método' : SalePaymentMethodLabels::label($key),
            $orderedMethodKeys,
        );

        $branches = [];
        $totalDayUsd = 0.0;

        foreach (array_values($branchIds) as $branchId) {
            $branchTotalUsd = 0.0;
            $chartValues = [];
            $methods = [];

            foreach ($orderedMethodKeys as $methodKey) {
                $totals = $indexed[$branchId][$methodKey] ?? [
                    'total_usd' => 0.0,
                    'collected_usd' => 0.0,
                    'collected_ves' => 0.0,
                    'usd_weighted_ves' => 0.0,
                ];

                $methodLabel = $methodKey === '__empty'
                    ? 'Sin método'
                    : SalePaymentMethodLabels::label($methodKey);

                $vesEquivalentForUsd = $this->resolveVesEquivalentForUsd(
                    $totals['collected_usd'],
                    $totals['usd_weighted_ves'],
                    $officialRate,
                );

                $chartValues[] = $totals['total_usd'];
                $branchTotalUsd += $totals['total_usd'];

                if ($totals['total_usd'] <= 0.0 && $totals['collected_ves'] <= 0.0) {
                    continue;
                }

                $methods[] = [
                    'key' => $methodKey,
                    'label' => $methodLabel,
                    'total_usd' => $totals['total_usd'],
                    'collected_usd' => $totals['collected_usd'],
                    'collected_ves' => $totals['collected_ves'],
                    'ves_equivalent_for_usd' => $vesEquivalentForUsd,
                    'legend_label' => $this->buildLegendLabel(
                        $methodLabel,
                        $totals['collected_usd'],
                        $totals['collected_ves'],
                        $vesEquivalentForUsd,
                    ),
                ];
            }

            $branchName = $branchNames[$branchId] ?? ('Sucursal #'.$branchId);
            $branches[] = [
                'branch_id' => $branchId,
                'branch_name' => Str::limit((string) $branchName, 28, '…'),
                'chart_values' => $chartValues,
                'methods' => $methods,
                'branch_total_usd' => round($branchTotalUsd, 2),
            ];

            $totalDayUsd += $branchTotalUsd;
        }

        return [
            'labels' => $labels,
            'branches' => $branches,
            'bcv_rate' => $officialRate,
            'total_day_usd' => round($totalDayUsd, 2),
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function orderedMethodKeys(array $keys): array
    {
        $preferred = SalePaymentMethodLabels::dashboardOrder();
        $ordered = [];

        foreach ($preferred as $key) {
            if (in_array($key, $keys, true)) {
                $ordered[] = $key;
            }
        }

        $remaining = array_diff($keys, $ordered);
        sort($remaining);

        return array_merge($ordered, array_values($remaining));
    }

    private function resolveVesEquivalentForUsd(
        float $collectedUsd,
        float $usdWeightedVes,
        ?float $officialRate,
    ): ?float {
        if ($collectedUsd <= 0.0) {
            return null;
        }

        if ($usdWeightedVes > 0.0) {
            return round($usdWeightedVes, 2);
        }

        if ($officialRate !== null && $officialRate > 0.0) {
            return round($collectedUsd * $officialRate, 2);
        }

        return null;
    }

    private function buildLegendLabel(
        string $label,
        float $collectedUsd,
        float $collectedVes,
        ?float $vesEquivalentForUsd,
    ): string {
        $detailParts = [];

        if ($collectedUsd > 0.0) {
            $usdLine = $this->formatUsd($collectedUsd);
            if ($vesEquivalentForUsd !== null && $vesEquivalentForUsd > 0.0) {
                $usdLine .= ' · ≈ '.$this->formatBs($vesEquivalentForUsd);
            }
            $detailParts[] = $usdLine;
        }

        if ($collectedVes > 0.0) {
            $detailParts[] = $this->formatBs($collectedVes);
        }

        if ($detailParts === []) {
            return $label;
        }

        return $label.' — '.implode(' · ', $detailParts);
    }

    /**
     * @param  list<int>  $branchIds
     * @param  Collection<int, string>|array<int, string>  $branchNames
     * @return array{
     *     labels: list<string>,
     *     branches: list<array<string, mixed>>,
     *     bcv_rate: float|null,
     *     total_day_usd: float
     * }
     */
    private function emptyChart(
        ?float $officialRate = null,
        array $branchIds = [],
        $branchNames = [],
    ): array {
        $branches = [];

        foreach (array_values($branchIds) as $branchId) {
            $branchName = $branchNames[$branchId] ?? ('Sucursal #'.$branchId);
            $branches[] = [
                'branch_id' => $branchId,
                'branch_name' => Str::limit((string) $branchName, 28, '…'),
                'chart_values' => [],
                'methods' => [],
                'branch_total_usd' => 0.0,
            ];
        }

        return [
            'labels' => [],
            'branches' => $branches,
            'bcv_rate' => $officialRate,
            'total_day_usd' => 0.0,
        ];
    }

    private function formatUsd(float $amount): string
    {
        return '$'.number_format($amount, 2, ',', '.');
    }

    private function formatBs(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }
}
