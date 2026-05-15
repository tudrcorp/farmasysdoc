<?php

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Filament\Widgets\Support\IosSalesTrendChartStyle;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BranchSalesTotalChart extends ChartWidget
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.ios-sales-chart';

    private const FILTER_TODAY = 'today';

    private const FILTER_LAST_7_DAYS = 'last_7_days';

    private const FILTER_LAST_30_DAYS = 'last_30_days';

    private const FILTER_CURRENT_MONTH = 'current_month';

    private const FILTER_CURRENT_YEAR = 'current_year';

    private const FILTER_ALL_TIME = 'all_time';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Total de ventas por sucursal';

    protected ?string $maxHeight = '320px';

    protected string $color = 'success';

    public function mount(): void
    {
        if ($this->filter === null || $this->filter === '') {
            $this->filter = self::FILTER_CURRENT_MONTH;
        }

        parent::mount();
    }

    public function updatedFilter(?string $value): void
    {
        $this->cachedData = null;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    public function getDescription(): string|Htmlable|null
    {
        $total = $this->totalForSelectedFilter();

        return 'Período: '.$this->selectedFilterLabel().' · Total: '.$this->formatUsd($total);
    }

    /**
     * @return array<string, string>|null
     */
    protected function getFilters(): ?array
    {
        return [
            self::FILTER_TODAY => 'Hoy',
            self::FILTER_LAST_7_DAYS => 'Últimos 7 días',
            self::FILTER_LAST_30_DAYS => 'Últimos 30 días',
            self::FILTER_CURRENT_MONTH => 'Mes actual',
            self::FILTER_CURRENT_YEAR => 'Año actual',
            self::FILTER_ALL_TIME => 'Histórico',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $query = Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('branch_id')
            ->whereNotNull('sold_at');

        BranchAuthScope::applyToSalesQuery($query);
        $this->applyPeriodFilter($query);

        $rows = $query
            ->selectRaw('branch_id, SUM(total) as total_sales')
            ->groupBy('branch_id')
            ->orderByDesc('total_sales')
            ->limit(20)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => 'Total vendido por sucursal',
                        'data' => [],
                        'backgroundColor' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        $branchIds = $rows
            ->pluck('branch_id')
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $branchNames = Branch::query()
            ->whereIn('id', $branchIds)
            ->pluck('name', 'id')
            ->all();

        $labels = [];
        $data = [];

        foreach ($rows as $row) {
            $branchId = (int) $row->branch_id;
            $branchName = $branchNames[$branchId] ?? ('Sucursal #'.$branchId);

            $labels[] = Str::limit((string) $branchName, 34, '…');
            $data[] = round((float) $row->total_sales, 2);
        }

        $count = count($data);

        return [
            'datasets' => [
                [
                    'label' => $this->datasetLabelForSelectedFilter(),
                    'data' => $data,
                    'backgroundColor' => IosSalesTrendChartStyle::vividBarFills($count),
                    'hoverBackgroundColor' => IosSalesTrendChartStyle::vividBarHovers($count),
                    'borderColor' => IosSalesTrendChartStyle::barBorderColors($count),
                    'hoverBorderColor' => 'rgba(255, 255, 255, 0.5)',
                    'borderWidth' => 1,
                    'hoverBorderWidth' => 2,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return IosSalesTrendChartStyle::verticalChartOptions();
    }

    /**
     * @param  Builder<Sale>  $query
     */
    private function applyPeriodFilter(Builder $query): void
    {
        [$from, $to] = $this->resolveDateRangeForFilter();

        if ($from instanceof CarbonInterface && $to instanceof CarbonInterface) {
            $query->whereBetween('sold_at', [$from, $to]);
        }
    }

    /**
     * @return array{0: CarbonInterface|null, 1: CarbonInterface|null}
     */
    private function resolveDateRangeForFilter(): array
    {
        $now = now();

        return match ($this->resolvedFilter()) {
            self::FILTER_TODAY => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            self::FILTER_LAST_7_DAYS => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            self::FILTER_LAST_30_DAYS => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            self::FILTER_CURRENT_MONTH => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            self::FILTER_CURRENT_YEAR => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            self::FILTER_ALL_TIME => [null, null],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
        };
    }

    private function resolvedFilter(): string
    {
        $filter = (string) ($this->filter ?? self::FILTER_CURRENT_MONTH);

        return in_array($filter, [
            self::FILTER_TODAY,
            self::FILTER_LAST_7_DAYS,
            self::FILTER_LAST_30_DAYS,
            self::FILTER_CURRENT_MONTH,
            self::FILTER_CURRENT_YEAR,
            self::FILTER_ALL_TIME,
        ], true) ? $filter : self::FILTER_CURRENT_MONTH;
    }

    private function datasetLabelForSelectedFilter(): string
    {
        return match ($this->resolvedFilter()) {
            self::FILTER_TODAY => 'Total vendido por sucursal (hoy)',
            self::FILTER_LAST_7_DAYS => 'Total vendido por sucursal (últimos 7 días)',
            self::FILTER_LAST_30_DAYS => 'Total vendido por sucursal (últimos 30 días)',
            self::FILTER_CURRENT_MONTH => 'Total vendido por sucursal (mes actual)',
            self::FILTER_CURRENT_YEAR => 'Total vendido por sucursal (año actual)',
            self::FILTER_ALL_TIME => 'Total vendido por sucursal (histórico)',
            default => 'Total vendido por sucursal',
        };
    }

    private function selectedFilterLabel(): string
    {
        return match ($this->resolvedFilter()) {
            self::FILTER_TODAY => 'Hoy',
            self::FILTER_LAST_7_DAYS => 'Últimos 7 días',
            self::FILTER_LAST_30_DAYS => 'Últimos 30 días',
            self::FILTER_CURRENT_MONTH => 'Mes actual',
            self::FILTER_CURRENT_YEAR => 'Año actual',
            self::FILTER_ALL_TIME => 'Histórico',
            default => 'Mes actual',
        };
    }

    private function totalForSelectedFilter(): float
    {
        $query = Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at');

        BranchAuthScope::applyToSalesQuery($query);
        $this->applyPeriodFilter($query);

        return round((float) $query->sum('total'), 2);
    }

    private function formatUsd(float $amount): string
    {
        return '$'.number_format($amount, 2, ',', '.');
    }

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdministrator();
    }
}
