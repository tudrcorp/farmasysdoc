<?php

namespace App\Filament\Widgets\Marketing;

use App\Models\User;
use App\Services\Marketing\MarketingAnalyticsService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class MarketingTopProductsChartWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Productos más vendidos';

    protected ?string $description = 'Unidades vendidas — mismo lenguaje visual que el resumen.';

    protected ?string $maxHeight = '360px';

    protected string $color = 'primary';

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $chart = app(MarketingAnalyticsService::class)->topProductsChart(10);
        $n = count($chart['data']);

        return [
            'datasets' => [
                [
                    'label' => 'Unidades',
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
            MarketingBarChartStyle::commonOptions(isHorizontal: true),
            [
                'indexAxis' => 'y',
                'scales' => [
                    'x' => array_replace_recursive($scale, [
                        'beginAtZero' => true,
                        'ticks' => [
                            'font' => [
                                'size' => 11,
                                'weight' => '600',
                            ],
                        ],
                    ]),
                    'y' => array_replace_recursive($scale, [
                        'ticks' => [
                            'font' => [
                                'size' => 10,
                                'weight' => '600',
                            ],
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
