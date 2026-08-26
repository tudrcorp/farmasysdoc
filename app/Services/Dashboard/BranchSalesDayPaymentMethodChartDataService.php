<?php

namespace App\Services\Dashboard;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Sale;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Sales\SaleCollectedMoneyAggregator;
use App\Support\Sales\SaleCollectedMoneyAttributor;
use App\Support\Sales\SalePaymentMethodLabels;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Cobro real del día por sucursal: VES en bolívares, USD en dólares, sin convertir.
 */
final class BranchSalesDayPaymentMethodChartDataService
{
    public function __construct(
        private readonly SaleCollectedMoneyAggregator $collectedMoneyAggregator,
    ) {}

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
     *         cachea: array<string, mixed>,
     *         branch_total_usd: float,
     *         branch_total_ves: float
     *     }>,
     *     bcv_rate: float|null,
     *     total_day_usd: float,
     *     total_day_ves: float
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

        $sales = Sale::query()
            ->with(['conciliationCachea', 'posTerminal'])
            ->excludingInternalBranchTransfers()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sold_at', [$dayStart, $dayEnd])
            ->get();

        $indexed = [];
        $methodKeysFound = [];

        foreach ($sales->groupBy('branch_id') as $branchId => $branchSales) {
            $channels = $this->collectedMoneyAggregator->collectedByChannel($branchSales);
            $indexed[(int) $branchId] = [
                'channels' => $channels,
                'cachea' => $this->collectedMoneyAggregator->cacheaCuotaBreakdown($branchSales),
            ];

            foreach (array_keys($channels) as $key) {
                $methodKeysFound[$key] = true;
            }
        }

        if ($methodKeysFound === []) {
            return $this->emptyChart($officialRate, $branchIds, $branchNames);
        }

        $orderedMethodKeys = $this->collectedMoneyAggregator->orderedChannelKeys(array_keys($methodKeysFound));
        $labels = array_map(
            fn (string $key): string => $this->channelChartLabel($key),
            $orderedMethodKeys,
        );

        $branches = [];
        $totalDayUsd = 0.0;
        $totalDayVes = 0.0;

        foreach (array_values($branchIds) as $branchId) {
            $methods = [];
            $chartValues = [];
            $branchTotalUsd = 0.0;
            $branchTotalVes = 0.0;
            $branchPayload = $indexed[$branchId] ?? [
                'channels' => [],
                'cachea' => $this->emptyCacheaBreakdown(),
            ];

            foreach ($orderedMethodKeys as $methodKey) {
                $totals = $branchPayload['channels'][$methodKey] ?? [
                    'usd' => 0.0,
                    'ves' => 0.0,
                    'count' => 0,
                ];

                $collectedUsd = round((float) $totals['usd'], 2);
                $collectedVes = round((float) $totals['ves'], 2);
                $methodLabel = SalePaymentMethodLabels::label($methodKey);

                $chartValues[] = $collectedUsd > 0.00001 ? $collectedUsd : $collectedVes;
                $branchTotalUsd = round($branchTotalUsd + $collectedUsd, 2);
                $branchTotalVes = round($branchTotalVes + $collectedVes, 2);

                if ($collectedUsd <= 0.00001 && $collectedVes <= 0.00001) {
                    continue;
                }

                $methods[] = [
                    'key' => $methodKey,
                    'label' => $methodLabel,
                    'total_usd' => 0.0,
                    'collected_usd' => $collectedUsd,
                    'collected_ves' => $collectedVes,
                    'ves_equivalent_for_usd' => null,
                    'legend_label' => $this->buildLegendLabel($methodLabel, $collectedUsd, $collectedVes),
                ];
            }

            $branchName = $branchNames[$branchId] ?? ('Sucursal #'.$branchId);
            $branches[] = [
                'branch_id' => $branchId,
                'branch_name' => Str::limit((string) $branchName, 28, '…'),
                'chart_values' => $chartValues,
                'methods' => $methods,
                'cachea' => $this->presentCacheaBreakdown($branchPayload['cachea']),
                'branch_total_usd' => $branchTotalUsd,
                'branch_total_ves' => $branchTotalVes,
            ];

            $totalDayUsd = round($totalDayUsd + $branchTotalUsd, 2);
            $totalDayVes = round($totalDayVes + $branchTotalVes, 2);
        }

        return [
            'labels' => $labels,
            'branches' => $branches,
            'bcv_rate' => $officialRate,
            'total_day_usd' => $totalDayUsd,
            'total_day_ves' => $totalDayVes,
        ];
    }

    private function channelChartLabel(string $key): string
    {
        $label = SalePaymentMethodLabels::label($key);

        return in_array($key, SaleCollectedMoneyAttributor::usdPaymentMethods(), true)
            ? $label.' (USD)'
            : $label.' (Bs)';
    }

    private function buildLegendLabel(string $label, float $collectedUsd, float $collectedVes): string
    {
        $detailParts = [];

        if ($collectedUsd > 0.00001) {
            $detailParts[] = $this->formatUsd($collectedUsd);
        }

        if ($collectedVes > 0.00001) {
            $detailParts[] = $this->formatBs($collectedVes);
        }

        if ($detailParts === []) {
            return $label;
        }

        return $label.' — '.implode(' · ', $detailParts);
    }

    /**
     * @param  array{
     *     sale_count: int,
     *     cuota_usd: float,
     *     collected_usd: float,
     *     collected_ves: float,
     *     remainder_usd: float,
     *     channels: list<array<string, mixed>>
     * }  $cachea
     * @return array{
     *     sale_count: int,
     *     cuota_usd: float,
     *     collected_usd: float,
     *     collected_ves: float,
     *     remainder_usd: float,
     *     summary_label: string,
     *     channels: list<array{channel: string, label: string, count: int, cuota_usd: float, collected_usd: float, collected_ves: float, legend_label: string}>
     * }
     */
    private function presentCacheaBreakdown(array $cachea): array
    {
        $channels = [];
        foreach ($cachea['channels'] as $row) {
            $channels[] = [
                ...$row,
                'legend_label' => $this->buildCacheaChannelLabel($row),
            ];
        }

        $summaryParts = [];
        if ((int) $cachea['sale_count'] === 1) {
            $summaryParts[] = '1 venta';
        } else {
            $summaryParts[] = ((int) $cachea['sale_count']).' ventas';
        }

        if ((float) $cachea['cuota_usd'] > 0.00001) {
            $summaryParts[] = 'cuota '.$this->formatUsd((float) $cachea['cuota_usd']);
        }

        return [
            'sale_count' => (int) $cachea['sale_count'],
            'cuota_usd' => (float) $cachea['cuota_usd'],
            'collected_usd' => (float) $cachea['collected_usd'],
            'collected_ves' => (float) $cachea['collected_ves'],
            'remainder_usd' => (float) $cachea['remainder_usd'],
            'summary_label' => 'Cashea — '.implode(' · ', $summaryParts),
            'channels' => $channels,
        ];
    }

    /**
     * @param  array{label: string, count: int, cuota_usd: float, collected_usd: float, collected_ves: float}  $row
     */
    private function buildCacheaChannelLabel(array $row): string
    {
        $countLabel = ((int) $row['count'] === 1) ? '1 cuota' : ((int) $row['count']).' cuotas';
        $parts = ['Vía '.$row['label'], $countLabel];

        if ((float) $row['cuota_usd'] > 0.00001) {
            $parts[] = 'cuota '.$this->formatUsd((float) $row['cuota_usd']);
        }

        $collectedUsd = (float) $row['collected_usd'];
        $collectedVes = (float) $row['collected_ves'];
        $sameUsdOnly = $collectedVes <= 0.00001
            && abs($collectedUsd - (float) $row['cuota_usd']) < 0.00001;

        $collectedParts = [];
        if (! $sameUsdOnly && $collectedUsd > 0.00001) {
            $collectedParts[] = $this->formatUsd($collectedUsd);
        }
        if ($collectedVes > 0.00001) {
            $collectedParts[] = $this->formatBs($collectedVes);
        }

        if ($collectedParts !== []) {
            $parts[] = 'cobrada '.$this->joinCollected($collectedParts);
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  list<string>  $parts
     */
    private function joinCollected(array $parts): string
    {
        if (count($parts) === 1) {
            return 'en '.$parts[0];
        }

        return 'en '.implode(' y ', $parts);
    }

    /**
     * @return array{
     *     sale_count: int,
     *     cuota_usd: float,
     *     collected_usd: float,
     *     collected_ves: float,
     *     remainder_usd: float,
     *     channels: list<array<string, mixed>>
     * }
     */
    private function emptyCacheaBreakdown(): array
    {
        return [
            'sale_count' => 0,
            'cuota_usd' => 0.0,
            'collected_usd' => 0.0,
            'collected_ves' => 0.0,
            'remainder_usd' => 0.0,
            'channels' => [],
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @param  Collection<int, string>|array<int, string>  $branchNames
     * @return array{
     *     labels: list<string>,
     *     branches: list<array<string, mixed>>,
     *     bcv_rate: float|null,
     *     total_day_usd: float,
     *     total_day_ves: float
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
                'cachea' => $this->presentCacheaBreakdown($this->emptyCacheaBreakdown()),
                'branch_total_usd' => 0.0,
                'branch_total_ves' => 0.0,
            ];
        }

        return [
            'labels' => [],
            'branches' => $branches,
            'bcv_rate' => $officialRate,
            'total_day_usd' => 0.0,
            'total_day_ves' => 0.0,
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
