<?php

namespace App\Filament\Widgets\Marketing;

use App\Models\User;
use App\Services\Marketing\MarketingAnalyticsService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class MarketingBranchRevenueChartWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Ingresos por sucursal';

    protected ?string $description = 'Ventas completadas — barras sobrias, interactividad completa.';

    protected ?string $maxHeight = '340px';

    protected string $color = 'info';

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $chart = app(MarketingAnalyticsService::class)->branchRevenueChart(12);
        $n = count($chart['data']);

        return [
            'datasets' => [
                [
                    'label' => 'Ingresos',
                    'data' => $chart['data'],
                    'backgroundColor' => MarketingBarChartStyle::seriousBarFills($n),
                    'hoverBackgroundColor' => MarketingBarChartStyle::seriousBarHovers($n),
                    'borderColor' => MarketingBarChartStyle::barBorderColors($n),
                    'hoverBorderColor' => 'rgba(255, 255, 255, 0.42)',
                    'borderWidth' => 1,
                    'hoverBorderWidth' => 2,
                    'borderRadius' => 12,
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
        $scale = MarketingBarChartStyle::scaleStyling();

        return array_replace_recursive(
            MarketingBarChartStyle::commonOptions(isHorizontal: false),
            [
                'scales' => [
                    'y' => array_replace_recursive($scale, [
                        'beginAtZero' => true,
                        'ticks' => [
                            'font' => [
                                'size' => 11,
                                'weight' => '500',
                            ],
                        ],
                    ]),
                    'x' => array_replace_recursive($scale, [
                        'ticks' => [
                            'font' => [
                                'size' => 10,
                                'weight' => '600',
                            ],
                            'maxRotation' => 45,
                            'minRotation' => 0,
                        ],
                    ]),
                ],
            ]
        );
    }

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessMarketingModule();
    }
}
