<?php

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Filament\Widgets\Concerns\InteractsWithDashboardBranchFilter;
use App\Filament\Widgets\Support\BrandChartPalette;
use App\Filament\Widgets\Support\IosSalesTrendChartStyle;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use App\Support\Filament\DashboardDateRangeFilter;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManagementBranchSalesByMonthChart extends ChartWidget
{
    use InteractsWithDashboardBranchFilter;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.ios-sales-chart';

    protected static ?int $sort = 1;

    protected ?string $heading = 'Ventas por mes (sucursales asignadas)';

    protected ?string $maxHeight = '320px';

    protected string $color = 'info';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getDescription(): string|Htmlable|null
    {
        $total = $this->totalPeriodAmount();

        return 'Período '.DashboardDateRangeFilter::label().' · Total: '.$this->formatUsd($total).$this->dashboardBranchFilterSuffix();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $branchIds = $this->dashboardBranchIdsForCharts();
        if ($branchIds === []) {
            return [
                'datasets' => [],
                'labels' => $this->monthLabels(),
            ];
        }

        $monthExpression = $this->monthGroupExpression();
        $totalCast = $this->totalCastExpression();
        $rows = Sale::query()
            ->excludingInternalBranchTransfers()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sold_at', [$this->dashboardDateFrom(), $this->dashboardDateTo()])
            ->selectRaw("branch_id, {$monthExpression} as ym, SUM({$totalCast}) as amount")
            ->groupBy('branch_id', DB::raw($monthExpression))
            ->get();

        $branchNames = Branch::query()
            ->whereIn('id', $branchIds)
            ->pluck('name', 'id');

        $indexed = [];
        foreach ($rows as $row) {
            $branchId = (int) $row->branch_id;
            $monthKey = (string) $row->ym;
            $indexed[$branchId][$monthKey] = round((float) $row->amount, 2);
        }

        $monthKeys = $this->monthKeys();
        $fills = BrandChartPalette::branchBarFills(count($branchIds));
        $hovers = BrandChartPalette::branchBarHovers(count($branchIds));
        $borders = BrandChartPalette::branchBarBorderColors(count($branchIds));

        $datasets = [];
        foreach (array_values($branchIds) as $index => $branchId) {
            $series = [];
            foreach ($monthKeys as $monthKey) {
                $series[] = (float) ($indexed[$branchId][$monthKey] ?? 0.0);
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
            'labels' => $this->monthLabels(),
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
     * @return list<string>
     */
    private function monthLabels(): array
    {
        $spansYears = $this->dashboardDateFrom()->year !== $this->dashboardDateTo()->year;

        return array_map(
            static function (string $monthKey) use ($spansYears): string {
                $month = CarbonImmutable::createFromFormat('!Y-m', $monthKey)->locale('es');
                $label = ucfirst(rtrim($month->translatedFormat('M'), '.'));

                return $spansYears ? $label.' '.$month->format('y') : $label;
            },
            $this->monthKeys(),
        );
    }

    /**
     * @return list<string>
     */
    private function monthKeys(): array
    {
        $keys = [];
        $cursor = $this->dashboardDateFrom()->startOfMonth();
        $end = $this->dashboardDateTo()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($end)) {
            $keys[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonthNoOverflow();
        }

        return $keys;
    }

    private function totalPeriodAmount(): float
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

    private function monthGroupExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', sold_at)",
            'pgsql' => "to_char(sold_at::timestamp, 'YYYY-MM')",
            default => "DATE_FORMAT(sold_at, '%Y-%m')",
        };
    }

    private function totalCastExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => 'CAST(total AS REAL)',
            default => 'CAST(total AS DECIMAL(14,2))',
        };
    }

    private function formatUsd(float $amount): string
    {
        return '$'.number_format($amount, 2, ',', '.');
    }

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->hasGerenciaRole();
    }
}
