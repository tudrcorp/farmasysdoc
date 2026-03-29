<?php

namespace App\Filament\Widgets\Support;

use App\Filament\Widgets\Marketing\MarketingBarChartStyle;

/**
 * Opciones Chart.js interactivas estilo iOS (tooltips, hover, touch). Colores de barra: {@see BrandChartPalette}.
 */
final class IosProductSalesChartStyle
{
    /**
     * @return array<string, mixed>
     */
    public static function iosChartOptions(bool $horizontal = true): array
    {
        $axis = $horizontal ? 'y' : 'x';

        /*
         * No usar RawJs dentro de este array: @js() en la vista hace json_encode y los Js anidados
         * quedan como {} — Chart.js pierde callbacks y el tooltip deja de funcionar.
         */
        return array_replace_recursive(
            MarketingBarChartStyle::commonOptions($horizontal),
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
                        'categoryPercentage' => 0.88,
                        'barPercentage' => 0.58,
                    ],
                ],
                'interaction' => [
                    'mode' => 'index',
                    'intersect' => false,
                    'axis' => $axis,
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
                            'padding' => 16,
                            'font' => [
                                'size' => 11,
                                'weight' => '600',
                            ],
                        ],
                    ],
                    'tooltip' => [
                        'enabled' => true,
                        'intersect' => false,
                        'mode' => 'index',
                        'position' => 'nearest',
                        'backgroundColor' => 'rgba(42, 32, 28, 0.92)',
                        'titleColor' => 'rgba(255, 250, 245, 0.98)',
                        'bodyColor' => 'rgba(254, 243, 199, 0.95)',
                        'borderColor' => 'rgba(251, 191, 36, 0.35)',
                        'borderWidth' => 1,
                        'cornerRadius' => 11,
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
                        'borderRadius' => 10,
                        'borderSkipped' => false,
                    ],
                ],
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function horizontalScaleConfig(): array
    {
        $scale = MarketingBarChartStyle::scaleStyling();

        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => array_replace_recursive($scale, [
                    'beginAtZero' => true,
                    'ticks' => [
                        'font' => [
                            'size' => 9,
                            'weight' => '500',
                        ],
                    ],
                ]),
                'y' => array_replace_recursive($scale, [
                    'ticks' => [
                        'autoSkip' => false,
                        'font' => [
                            'size' => 8,
                            'weight' => '500',
                        ],
                    ],
                ]),
            ],
        ];
    }
}
