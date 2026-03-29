<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Support\IosSalesTrendChartStyle;
use App\Services\Dashboard\SalesChartDataService;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class SalesChart extends ChartWidget
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.ios-sales-chart';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Ventas por periodo';

    protected ?string $description = 'Meses del año en curso: vista anual o desglose diario por mes.';

    protected ?string $maxHeight = '320px';

    protected string $color = 'primary';

    public function mount(): void
    {
        if ($this->filter === null || $this->filter === '') {
            $this->filter = SalesChartDataService::filterMonthsSummaryKey();
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

    /**
     * @return array<string, string>|null
     */
    protected function getFilters(): ?array
    {
        $year = (int) now()->year;

        $filters = [
            SalesChartDataService::filterMonthsSummaryKey() => __('Por mes · año :year', ['year' => $year]),
        ];

        for ($month = 1; $month <= 12; $month++) {
            $d = now()->setDate($year, $month, 1)->startOfMonth();
            $key = $d->format('Y-m');
            $monthName = ucfirst($d->locale('es')->translatedFormat('F'));
            $filters[$key] = $monthName.' · '.__('por día');
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $service = app(SalesChartDataService::class);
        $mode = $this->resolvedFilter();
        $year = (int) now()->year;

        if ($mode === SalesChartDataService::filterMonthsSummaryKey()) {
            $chart = $service->totalsForCalendarYear($year);
            $label = __('Total vendido (por mes · :year)', ['year' => $year]);
        } else {
            $chart = $service->totalsByDayInMonth(Carbon::parse($mode.'-01')->startOfMonth());
            $label = __('Total vendido (por día)');
        }

        $n = count($chart['data']);

        if ($n === 0) {
            return [
                'datasets' => [
                    [
                        'label' => $label,
                        'data' => [],
                        'backgroundColor' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => $label,
                    'data' => $chart['data'],
                    'backgroundColor' => IosSalesTrendChartStyle::vividBarFills($n),
                    'hoverBackgroundColor' => IosSalesTrendChartStyle::vividBarHovers($n),
                    'borderColor' => IosSalesTrendChartStyle::barBorderColors($n),
                    'hoverBorderColor' => 'rgba(255, 255, 255, 0.5)',
                    'borderWidth' => 1,
                    'hoverBorderWidth' => 2,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => $chart['labels'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return IosSalesTrendChartStyle::verticalChartOptions();
    }

    private function resolvedFilter(): string
    {
        $f = $this->filter ?? SalesChartDataService::filterMonthsSummaryKey();
        $currentYear = (int) now()->year;

        if ($f === SalesChartDataService::filterMonthsSummaryKey()) {
            return $f;
        }

        if (is_string($f) && preg_match('/^(\d{4})-(\d{2})$/', $f, $m) === 1) {
            $y = (int) $m[1];
            $month = (int) $m[2];
            if ($y === $currentYear && $month >= 1 && $month <= 12) {
                return $f;
            }
        }

        return SalesChartDataService::filterMonthsSummaryKey();
    }
}
