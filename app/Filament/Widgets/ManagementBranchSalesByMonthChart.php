<?php

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Filament\Widgets\Concerns\InteractsWithDashboardBranchFilter;
use App\Filament\Widgets\Support\BrandChartPalette;
use App\Filament\Widgets\Support\IosSalesTrendChartStyle;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
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

    protected ?string $description = 'Comparativo mensual por cada sucursal asociada al usuario de gerencia.';

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
        $year = (int) now()->year;
        $total = $this->totalYearAmount($year);

        return 'Año '.$year.' · Total: '.$this->formatUsd($total).$this->dashboardBranchFilterSuffix();
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

        $year = (int) now()->year;
        $monthExpression = $this->monthGroupExpression();
        $totalCast = $this->totalCastExpression();
        $rows = Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sold_at', [
                now()->setDate($year, 1, 1)->startOfDay(),
                now()->setDate($year, 12, 31)->endOfDay(),
            ])
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

        $monthKeys = $this->monthKeys($year);
        $fills = BrandChartPalette::barFills(count($branchIds));
        $hovers = BrandChartPalette::barHovers(count($branchIds));
        $borders = BrandChartPalette::barBorderColors(count($branchIds));

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
                'backgroundColor' => $fills[$index] ?? 'rgba(54, 162, 235, 0.82)',
                'hoverBackgroundColor' => $hovers[$index] ?? 'rgba(54, 162, 235, 0.96)',
                'borderColor' => $borders[$index] ?? 'rgba(255, 255, 255, 0.28)',
                'hoverBorderColor' => 'rgba(255, 255, 255, 0.55)',
                'borderWidth' => 1,
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
        $labels = [];
        $year = (int) now()->year;

        for ($month = 1; $month <= 12; $month++) {
            $labels[] = ucfirst(now()->setDate($year, $month, 1)->locale('es')->translatedFormat('M'));
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    private function monthKeys(int $year): array
    {
        $keys = [];

        for ($month = 1; $month <= 12; $month++) {
            $keys[] = now()->setDate($year, $month, 1)->format('Y-m');
        }

        return $keys;
    }

    private function totalYearAmount(int $year): float
    {
        $branchIds = $this->dashboardBranchIdsForCharts();
        if ($branchIds === []) {
            return 0.0;
        }

        return round((float) Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sold_at', [
                now()->setDate($year, 1, 1)->startOfDay(),
                now()->setDate($year, 12, 31)->endOfDay(),
            ])
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
