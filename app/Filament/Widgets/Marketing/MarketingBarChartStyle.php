<?php

namespace App\Filament\Widgets\Marketing;

use App\Filament\Widgets\Support\BrandChartPalette;

/**
 * Gráficos del hub de marketing: opciones tipo iOS discretas (paleta vía {@see BrandChartPalette}).
 */
final class MarketingBarChartStyle
{
    /**
     * @return list<string>
     */
    public static function seriousBarFills(int $count): array
    {
        return BrandChartPalette::barFills($count);
    }

    /**
     * @return list<string>
     */
    public static function seriousBarHovers(int $count): array
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
     * @return array<string, mixed>
     */
    public static function commonOptions(bool $isHorizontal): array
    {
        $axis = $isHorizontal ? 'y' : 'x';

        return [
            'animation' => [
                'duration' => 520,
                'easing' => 'easeOutCubic',
            ],
            'animations' => [
                'colors' => [
                    'duration' => 180,
                    'easing' => 'easeOutQuad',
                ],
                'numbers' => [
                    'duration' => 520,
                    'easing' => 'easeOutCubic',
                ],
            ],
            'datasets' => [
                'bar' => [
                    'categoryPercentage' => 0.68,
                    'barPercentage' => 0.74,
                ],
            ],
            'elements' => [
                'bar' => [
                    'borderWidth' => 1,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
            ],
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
                'axis' => $axis,
            ],
            'hover' => [
                'mode' => 'nearest',
                'intersect' => false,
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'enabled' => true,
                    'animation' => [
                        'duration' => 140,
                    ],
                    'backgroundColor' => 'rgba(28, 28, 30, 0.82)',
                    'titleColor' => 'rgba(255, 255, 255, 0.96)',
                    'bodyColor' => 'rgba(235, 235, 245, 0.92)',
                    'borderColor' => 'rgba(255, 255, 255, 0.1)',
                    'borderWidth' => 1,
                    'titleFont' => [
                        'size' => 12,
                        'weight' => '600',
                    ],
                    'bodyFont' => [
                        'size' => 11,
                        'weight' => '500',
                    ],
                    'padding' => 10,
                    'cornerRadius' => 11,
                    'displayColors' => true,
                    'boxPadding' => 5,
                    'caretPadding' => 8,
                    'caretSize' => 6,
                    'intersect' => false,
                    'mode' => 'index',
                    'position' => 'nearest',
                ],
            ],
            'transitions' => [
                'active' => [
                    'animation' => [
                        'duration' => 160,
                    ],
                ],
                'resize' => [
                    'animation' => [
                        'duration' => 0,
                    ],
                ],
            ],
        ];
    }

    /**
     * Rejilla y ejes muy discretos (legibles sin competir con la paleta de barras).
     *
     * @return array<string, mixed>
     */
    public static function scaleStyling(): array
    {
        $grid = [
            'display' => true,
            'drawOnChartArea' => true,
            'drawBorder' => false,
            'color' => 'rgba(148, 163, 184, 0.1)',
            'lineWidth' => 1,
        ];

        $border = [
            'display' => true,
            'color' => 'rgba(148, 163, 184, 0.12)',
        ];

        $tickFont = [
            'size' => 9,
            'weight' => '500',
        ];

        return [
            'grid' => $grid,
            'border' => $border,
            'ticks' => [
                'font' => $tickFont,
            ],
        ];
    }
}
