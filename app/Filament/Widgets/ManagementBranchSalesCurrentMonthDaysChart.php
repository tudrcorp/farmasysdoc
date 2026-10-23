<?php

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Filament\Widgets\Concerns\InteractsWithDashboardBranchFilter;
use App\Filament\Widgets\Support\BrandChartPalette;
use App\Filament\Widgets\Support\IosSalesTrendChartStyle;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use App\Services\Dashboard\BranchSalesDayPaymentMethodChartDataService;
use App\Support\Filament\DashboardBranchFilter;
use App\Support\Filament\DashboardDateRangeFilter;
use App\Support\Finance\BcvRate;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManagementBranchSalesCurrentMonthDaysChart extends ChartWidget
{
    use InteractsWithDashboardBranchFilter;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.ios-sales-branch-days-chart';

    protected static ?int $sort = 0;

    protected ?string $heading = 'Ventas por día (sucursales visibles)';

    protected ?string $maxHeight = '320px';

    protected string $color = 'success';

    public ?string $drillDownDate = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $cachedDrillDownPayload = null;

    public function drillIntoDay(int $dayIndex): void
    {
        if (! $this->isDayOverviewMode()) {
            return;
        }

        $dayKeys = $this->dayKeysForRange();
        if (! isset($dayKeys[$dayIndex])) {
            return;
        }

        $dateKey = $dayKeys[$dayIndex];
        if ($this->totalAmountForDay($dateKey) <= 0.0) {
            return;
        }

        $this->drillDownDate = $dateKey;
        $this->cachedData = null;
        $this->cachedDrillDownPayload = null;
    }

    public function backToDayOverview(): void
    {
        $this->drillDownDate = null;
        $this->cachedData = null;
        $this->cachedDrillDownPayload = null;
    }

    public function isDayOverviewMode(): bool
    {
        return $this->drillDownDate === null || $this->drillDownDate === '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getDrillDownBranches(): array
    {
        return $this->drillDownPayload()['branches'] ?? [];
    }

    public function getDrillDownBcvRateFormatted(): ?string
    {
        $rate = $this->drillDownPayload()['bcv_rate'] ?? null;

        if (! is_numeric($rate) || (float) $rate <= 0.0) {
            return null;
        }

        return BcvRate::format((float) $rate).' Bs/USD';
    }

    public function getHeading(): string|Htmlable|null
    {
        if (! $this->isDayOverviewMode()) {
            $date = Carbon::parse($this->drillDownDate)->locale('es');

            return 'Cobro por método de pago · '.$date->translatedFormat('d \d\e F Y');
        }

        $user = Filament::auth()->user();
        if ($user instanceof User && $user->isCoordinator()) {
            return 'Ventas por día (sucursal asignada)';
        }

        return $this->heading;
    }

    public function getDescription(): string|Htmlable|null
    {
        if (! $this->isDayOverviewMode()) {
            $payload = $this->drillDownPayload();
            $parts = [
                count($payload['branches'] ?? []).' sucursales',
                'Cobrado USD: '.$this->formatUsd((float) ($payload['total_day_usd'] ?? 0.0)),
                'Cobrado VES: '.$this->formatBs((float) ($payload['total_day_ves'] ?? 0.0)),
            ];

            $bcv = $this->getDrillDownBcvRateFormatted();
            if ($bcv !== null) {
                $parts[] = 'Tasa BCV: '.$bcv;
            } else {
                $parts[] = 'Tasa BCV: no disponible para esta fecha';
            }

            return implode(' · ', $parts).$this->dashboardBranchFilterSuffix();
        }

        return $this->periodLabel().' · Total: '.$this->formatUsd($this->totalAmountForRange())
            .' · Pulsa un día con ventas para ver el detalle por método de pago'
            .$this->dashboardBranchFilterSuffix();
    }

    public function getColumnSpan(): int|string|array
    {
        $user = Filament::auth()->user();

        if ($user instanceof User && ($user->isAdministrator() || $user->hasGerenciaRole())) {
            return 'full';
        }

        return 1;
    }

    public function getChartStageKey(): string
    {
        return DashboardDateRangeFilter::rangeKey().'-'.($this->drillDownDate ?? 'overview');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        if (! $this->isDayOverviewMode()) {
            return $this->drillDownChartData();
        }

        return $this->dayOverviewChartData();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        $base = array_replace_recursive(
            IosSalesTrendChartStyle::verticalChartOptions(),
            [
                'animation' => [
                    'duration' => 560,
                    'easing' => 'easeInOutCubic',
                ],
                'animations' => [
                    'numbers' => [
                        'duration' => 560,
                        'easing' => 'easeInOutCubic',
                    ],
                ],
            ],
        );

        return $base;
    }

    protected function getMaxHeight(): ?string
    {
        if (! $this->isDayOverviewMode()) {
            return null;
        }

        return $this->maxHeight;
    }

    /**
     * @return array<string, mixed>
     */
    private function drillDownPayload(): array
    {
        if ($this->cachedDrillDownPayload !== null) {
            return $this->cachedDrillDownPayload;
        }

        if ($this->isDayOverviewMode()) {
            return $this->cachedDrillDownPayload = [];
        }

        return $this->cachedDrillDownPayload = app(BranchSalesDayPaymentMethodChartDataService::class)
            ->chartForDay(
                $this->resolvedBranchIdsForCurrentUser(),
                Carbon::parse($this->drillDownDate),
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function dayOverviewChartData(): array
    {
        $branchIds = $this->dashboardBranchIdsForCharts();
        $dayKeys = $this->dayKeysForRange();

        if ($branchIds === []) {
            return [
                'datasets' => [],
                'labels' => $this->dayLabels($dayKeys),
            ];
        }

        $dateExpression = $this->dateGroupExpression();
        $rows = Sale::query()
            ->excludingInternalBranchTransfers()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sold_at', [$this->dashboardDateFrom(), $this->dashboardDateTo()])
            ->selectRaw("branch_id, {$dateExpression} as sold_day, SUM(CAST(total AS DECIMAL(14,2))) as amount")
            ->groupBy('branch_id', DB::raw($dateExpression))
            ->get();

        $branchNames = Branch::query()
            ->whereIn('id', $branchIds)
            ->pluck('name', 'id');

        $indexed = [];
        foreach ($rows as $row) {
            $branchId = (int) $row->branch_id;
            $dayKey = (string) $row->sold_day;
            $indexed[$branchId][$dayKey] = round((float) $row->amount, 2);
        }

        $fills = BrandChartPalette::branchBarFills(count($branchIds));
        $hovers = BrandChartPalette::branchBarHovers(count($branchIds));
        $borders = BrandChartPalette::branchBarBorderColors(count($branchIds));

        $datasets = [];
        foreach (array_values($branchIds) as $index => $branchId) {
            $series = [];
            foreach ($dayKeys as $dayKey) {
                $series[] = (float) ($indexed[$branchId][$dayKey] ?? 0.0);
            }

            $branchName = $branchNames[$branchId] ?? ('Sucursal #'.$branchId);
            $datasets[] = [
                'label' => Str::limit((string) $branchName, 28, '…'),
                'data' => $series,
                'backgroundColor' => $fills[$index] ?? 'rgba(50, 196, 240, 1)',
                'hoverBackgroundColor' => $hovers[$index] ?? 'rgba(93, 212, 247, 1)',
                'borderColor' => $borders[$index] ?? 'rgba(20, 143, 181, 1)',
                'hoverBorderColor' => $borders[$index] ?? 'rgba(20, 143, 181, 1)',
                'borderWidth' => 2,
                'hoverBorderWidth' => 2,
                'borderRadius' => 8,
                'borderSkipped' => false,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $this->dayLabels($dayKeys),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function drillDownChartData(): array
    {
        $payload = $this->drillDownPayload();
        $labels = $payload['labels'] ?? [];
        $branches = $payload['branches'] ?? [];

        if ($labels === [] || $branches === []) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $fills = BrandChartPalette::branchBarFills(count($branches));
        $hovers = BrandChartPalette::branchBarHovers(count($branches));
        $borders = BrandChartPalette::branchBarBorderColors(count($branches));

        $datasets = [];
        foreach ($branches as $index => $branch) {
            $datasets[] = [
                'label' => $branch['branch_name'],
                'data' => $branch['chart_values'],
                'backgroundColor' => $fills[$index] ?? 'rgba(50, 196, 240, 1)',
                'hoverBackgroundColor' => $hovers[$index] ?? 'rgba(93, 212, 247, 1)',
                'borderColor' => $borders[$index] ?? 'rgba(20, 143, 181, 1)',
                'hoverBorderColor' => $borders[$index] ?? 'rgba(20, 143, 181, 1)',
                'borderWidth' => 2,
                'hoverBorderWidth' => 2,
                'borderRadius' => 8,
                'borderSkipped' => false,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    /**
     * @return list<int>
     */
    private function resolvedBranchIdsForCurrentUser(): array
    {
        return $this->dashboardBranchIdsForCharts();
    }

    private function periodLabel(): string
    {
        if (DashboardDateRangeFilter::isMonthToDate()) {
            return ucfirst($this->dashboardDateFrom()->locale('es')->translatedFormat('F Y'));
        }

        return DashboardDateRangeFilter::label();
    }

    /**
     * @return list<string>
     */
    private function dayKeysForRange(): array
    {
        $keys = [];
        $cursor = $this->dashboardDateFrom()->startOfDay();
        $end = $this->dashboardDateTo()->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $keys[] = $cursor->format('Y-m-d');
            $cursor = $cursor->addDay();
        }

        return $keys;
    }

    /**
     * @param  list<string>  $dayKeys
     * @return list<string>
     */
    private function dayLabels(array $dayKeys): array
    {
        $format = DashboardDateRangeFilter::isSingleMonth() ? 'd' : 'd/m';

        return array_map(
            static fn (string $dayKey): string => Carbon::parse($dayKey)->format($format),
            $dayKeys,
        );
    }

    private function dateGroupExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => 'date(sold_at)',
            'pgsql' => 'sold_at::date',
            default => 'DATE(sold_at)',
        };
    }

    private function totalAmountForRange(): float
    {
        $branchIds = $this->dashboardBranchIdsForCharts();
        if ($branchIds === []) {
            return 0.0;
        }

        return round((float) Sale::query()
            ->excludingInternalBranchTransfers()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sold_at', [$this->dashboardDateFrom(), $this->dashboardDateTo()])
            ->sum('total'), 2);
    }

    private function totalAmountForDay(string $dateKey): float
    {
        $branchIds = $this->dashboardBranchIdsForCharts();
        if ($branchIds === []) {
            return 0.0;
        }

        $day = Carbon::parse($dateKey);

        return round((float) Sale::query()
            ->excludingInternalBranchTransfers()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sold_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->sum('total'), 2);
    }

    private function formatUsd(float $amount): string
    {
        return '$'.number_format($amount, 2, ',', '.');
    }

    private function formatBs(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdministrator() || $user->hasGerenciaRole()) {
            return true;
        }

        return $user->isCoordinator()
            && DashboardBranchFilter::allowedBranchIdsForCurrentUser() !== [];
    }
}
