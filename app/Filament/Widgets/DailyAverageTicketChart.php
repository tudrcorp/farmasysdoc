<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardBranchFilter;
use App\Filament\Widgets\Support\BrandChartPalette;
use App\Filament\Widgets\Support\IosSalesTrendChartStyle;
use App\Models\User;
use App\Services\Dashboard\BranchDailyAverageTicketChartDataService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class DailyAverageTicketChart extends ChartWidget
{
    use InteractsWithDashboardBranchFilter;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.ios-daily-average-ticket-chart';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Ticket promedio';

    protected ?string $maxHeight = '320px';

    protected string $color = 'warning';

    public function getDescription(): string|Htmlable|null
    {
        $monthLabel = ucfirst(now()->locale('es')->translatedFormat('F Y'));

        return $monthLabel
            .' · Total diario ÷ clientes que compraron ese día por sucursal'
            .$this->dashboardBranchFilterSuffix();
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
        $payload = app(BranchDailyAverageTicketChartDataService::class)
            ->chartForCurrentMonth($this->dashboardBranchIdsForCharts());

        $branchCount = count($payload['datasets']);
        $fills = BrandChartPalette::branchBarFills(max(1, $branchCount));
        $hovers = BrandChartPalette::branchBarHovers(max(1, $branchCount));
        $borders = BrandChartPalette::branchBarBorderColors(max(1, $branchCount));

        $datasets = [];
        foreach ($payload['datasets'] as $index => $dataset) {
            $datasets[] = [
                'label' => Str::limit($dataset['label'], 28, '…'),
                'branchName' => $dataset['label'],
                'data' => $dataset['data'],
                'dailyTotals' => $dataset['daily_totals'],
                'customerCounts' => $dataset['customer_counts'],
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
            'labels' => $payload['labels'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return array_replace_recursive(
            IosSalesTrendChartStyle::verticalChartOptions(),
            [
                'animation' => [
                    'duration' => 560,
                    'easing' => 'easeInOutCubic',
                ],
            ],
        );
    }

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdministrator();
    }
}
