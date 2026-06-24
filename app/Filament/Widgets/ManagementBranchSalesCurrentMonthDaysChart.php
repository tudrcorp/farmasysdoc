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
use Carbon\Carbon;
use Carbon\CarbonInterface;
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

    public function mount(): void
    {
        if ($this->filter === null || $this->filter === '') {
            $this->filter = now()->format('Y-m');
        }

        parent::mount();
    }

    public function updatedFilter(?string $value): void
    {
        $this->drillDownDate = null;
        $this->cachedData = null;
        $this->cachedDrillDownPayload = null;
    }

    public function drillIntoDay(int $dayIndex): void
    {
        if (! $this->isDayOverviewMode()) {
            return;
        }

        $dayKeys = $this->dayKeysForSelectedMonth();
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

        return number_format((float) $rate, 6, ',', '.').' Bs/USD';
    }

    public function getHeading(): string|Htmlable|null
    {
        if (! $this->isDayOverviewMode()) {
            $date = Carbon::parse($this->drillDownDate)->locale('es');

            return 'Cobro por método de pago · '.$date->translatedFormat('d \d\e F Y');
        }

        return $this->heading;
    }

    public function getDescription(): string|Htmlable|null
    {
        if (! $this->isDayOverviewMode()) {
            $payload = $this->drillDownPayload();
            $parts = [
                count($payload['branches'] ?? []).' sucursales',
                'Total del día: '.$this->formatUsd((float) ($payload['total_day_usd'] ?? 0.0)),
            ];

            $bcv = $this->getDrillDownBcvRateFormatted();
            if ($bcv !== null) {
                $parts[] = 'Tasa BCV: '.$bcv;
            } else {
                $parts[] = 'Tasa BCV: no disponible para esta fecha';
            }

            return implode(' · ', $parts).$this->dashboardBranchFilterSuffix();
        }

        $month = $this->selectedMonth();
        $monthLabel = ucfirst($month->locale('es')->translatedFormat('F Y'));

        return $monthLabel.' · Total: '.$this->formatUsd($this->totalAmountForMonth($month))
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

    /**
     * @return array<string, string>|null
     */
    protected function getFilters(): ?array
    {
        $filters = [];

        for ($offset = 0; $offset < 24; $offset++) {
            $month = now()->startOfMonth()->subMonths($offset);
            $key = $month->format('Y-m');
            $filters[$key] = ucfirst($month->locale('es')->translatedFormat('F Y'));
        }

        return $filters;
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
            $methodCount = count($this->getCachedData()['labels'] ?? []);
            $height = max(320, min(460, 240 + ($methodCount * 22)));

            return $height.'px';
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
        $monthStart = $this->selectedMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        if ($branchIds === []) {
            return [
                'datasets' => [],
                'labels' => $this->dayLabels($monthStart->daysInMonth),
            ];
        }

        $dateExpression = $this->dateGroupExpression();
        $rows = Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sold_at', [$monthStart->copy()->startOfDay(), $monthEnd->copy()->endOfDay()])
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

        $dayKeys = $this->dayKeys($monthStart);
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
            'labels' => $this->dayLabels($monthStart->daysInMonth),
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

    private function selectedMonth(): CarbonInterface
    {
        $filter = $this->filter ?? now()->format('Y-m');

        if (is_string($filter) && preg_match('/^(\d{4})-(\d{2})$/', $filter, $matches) === 1) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];

            if ($month >= 1 && $month <= 12) {
                return now()->setDate($year, $month, 1)->startOfMonth();
            }
        }

        return now()->startOfMonth();
    }

    /**
     * @return list<string>
     */
    private function dayKeysForSelectedMonth(): array
    {
        return $this->dayKeys($this->selectedMonth());
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

    private function totalAmountForMonth(CarbonInterface $monthStart): float
    {
        $branchIds = $this->dashboardBranchIdsForCharts();
        if ($branchIds === []) {
            return 0.0;
        }

        return round((float) Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sold_at', [$monthStart->copy()->startOfDay(), $monthStart->copy()->endOfMonth()->endOfDay()])
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

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && ($user->hasGerenciaRole() || $user->isAdministrator());
    }
}
