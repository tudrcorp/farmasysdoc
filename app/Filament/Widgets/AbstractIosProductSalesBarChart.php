<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Support\BrandChartPalette;
use App\Filament\Widgets\Support\IosProductSalesChartStyle;
use Filament\Widgets\ChartWidget;

abstract class AbstractIosProductSalesBarChart extends ChartWidget
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.ios-product-sales-chart';

    protected ?string $maxHeight = '400px';

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Clase CSS para variante visual (glass iOS) en theme.css.
     */
    abstract public function getIosShellModifierClass(): string;

    /**
     * @return array{labels: list<string>, data: list<int>}
     */
    abstract protected function loadRanking(): array;

    /**
     * Barras cálidas intensas (más vendidos) o cálidas apagadas (menos vendidos).
     */
    abstract protected function useIntenseWarmBars(): bool;

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $chart = $this->loadRanking();
        $n = count($chart['data']);

        if ($n === 0) {
            return [
                'datasets' => [
                    [
                        'label' => __('Ventas'),
                        'data' => [],
                        'backgroundColor' => [],
                        'borderColor' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        $fills = $this->useIntenseWarmBars()
            ? BrandChartPalette::barFills($n)
            : BrandChartPalette::mutedBarFills($n);
        $hovers = $this->useIntenseWarmBars()
            ? BrandChartPalette::barHovers($n)
            : BrandChartPalette::mutedBarHovers($n);

        return [
            'datasets' => [
                [
                    'label' => __('Ventas con el producto'),
                    'data' => $chart['data'],
                    'backgroundColor' => $fills,
                    'hoverBackgroundColor' => $hovers,
                    'borderColor' => BrandChartPalette::barBorderColors($n),
                    'hoverBorderColor' => 'rgba(255, 255, 255, 0.45)',
                    'borderWidth' => 1,
                    'hoverBorderWidth' => 2,
                    'borderRadius' => 7,
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
        return array_replace_recursive(
            IosProductSalesChartStyle::iosChartOptions(true),
            IosProductSalesChartStyle::horizontalScaleConfig(),
        );
    }
}
