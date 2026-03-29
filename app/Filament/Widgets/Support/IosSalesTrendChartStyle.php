<?php

namespace App\Filament\Widgets\Support;

use App\Filament\Widgets\Marketing\MarketingBarChartStyle;

/**
 * Gráfico de ventas (barras verticales): misma paleta de marca que marketing + interacción sin RawJs.
 */
final class IosSalesTrendChartStyle
{
    /**
     * @return list<string>
     */
    public static function vividBarFills(int $count): array
    {
        return BrandChartPalette::barFills($count);
    }

    /**
     * @return list<string>
     */
    public static function vividBarHovers(int $count): array
    {
        return BrandChartPalette::barHovers($count);
    }

    /**
     * @return list<string>
     */
    public static function barBorderColors(int $count): array
    {
        return BrandChartPalette::barBorderColors($count);
    }

    /**
     * Barras verticales (meses o días en eje X).
     *
     * @return array<string, mixed>
     */
    public static function verticalChartOptions(): array
    {
        $scale = MarketingBarChartStyle::scaleStyling();

        return array_replace_recursive(
            MarketingBarChartStyle::commonOptions(false),
            [
                'animation' => [
                    'duration' => 520,
                    'easing' => 'easeOutCubic',
                ],
                'animations' => [
                    'numbers' => [
                        'duration' => 520,
                        'easing' => 'easeOutCubic',
                    ],
                ],
                'responsive' => true,
                'maintainAspectRatio' => false,
                'resizeDelay' => 0,
                'datasets' => [
                    'bar' => [
                        'categoryPercentage' => 0.72,
                        'barPercentage' => 0.72,
                    ],
                ],
                'interaction' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
                'hover' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'bottom',
                        'labels' => [
                            'usePointStyle' => true,
                            'pointStyle' => 'rectRounded',
                            'padding' => 8,
                            'boxWidth' => 10,
                            'boxHeight' => 6,
                            'font' => [
                                'size' => 10,
                                'weight' => '500',
                            ],
                        ],
                    ],
                    'tooltip' => [
                        'enabled' => true,
                        'intersect' => false,
                        'mode' => 'index',
                        'position' => 'nearest',
                        'backgroundColor' => 'rgba(28, 25, 23, 0.92)',
                        'titleColor' => 'rgba(255, 250, 245, 0.98)',
                        'bodyColor' => 'rgba(254, 243, 199, 0.95)',
                        'borderColor' => 'rgba(251, 191, 36, 0.4)',
                        'borderWidth' => 1,
                        'cornerRadius' => 12,
                        'padding' => 9,
                        'displayColors' => true,
                        'boxPadding' => 4,
                        'caretPadding' => 8,
                        'caretSize' => 6,
                        'titleFont' => [
                            'size' => 11,
                            'weight' => '600',
                        ],
                        'bodyFont' => [
                            'size' => 11,
                            'weight' => '500',
                        ],
                    ],
                ],
                'elements' => [
                    'bar' => [
                        'borderRadius' => 8,
                        'borderSkipped' => false,
                    ],
                ],
                'scales' => [
                    'x' => array_replace_recursive($scale, [
                        'ticks' => [
                            'maxRotation' => 45,
                            'minRotation' => 0,
                            'autoSkip' => true,
                            'font' => [
                                'size' => 9,
                                'weight' => '500',
                            ],
                        ],
                    ]),
                    'y' => array_replace_recursive($scale, [
                        'beginAtZero' => true,
                        'ticks' => [
                            'font' => [
                                'size' => 9,
                                'weight' => '500',
                            ],
                        ],
                    ]),
                ],
            ]
        );
    }
}
