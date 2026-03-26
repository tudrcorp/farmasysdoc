<?php

namespace App\Filament\Widgets\Marketing;

/**
 * Paleta sobria y opciones de interactividad compartidas para gráficos de barras del hub de marketing.
 */
final class MarketingBarChartStyle
{
    /**
     * Tonos corporativos (azul pizarra, teal, slate) con opacidad tipo “vidrio” sobre fondo claro.
     *
     * @return list<string>
     */
    public static function seriousBarFills(int $count): array
    {
        $palette = [
            'rgba(30, 58, 95, 0.88)',
            'rgba(15, 98, 109, 0.86)',
            'rgba(51, 65, 85, 0.88)',
            'rgba(21, 94, 117, 0.86)',
            'rgba(30, 64, 175, 0.82)',
            'rgba(55, 65, 81, 0.88)',
            'rgba(14, 116, 144, 0.86)',
            'rgba(71, 85, 105, 0.84)',
            'rgba(23, 37, 84, 0.88)',
            'rgba(13, 148, 136, 0.84)',
            'rgba(47, 69, 83, 0.88)',
            'rgba(8, 145, 178, 0.84)',
        ];

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = $palette[$i % count($palette)];
        }

        return $out;
    }

    /**
     * Misma familia cromática, más opaca al hover (contraste con el fondo glass).
     *
     * @return list<string>
     */
    public static function seriousBarHovers(int $count): array
    {
        $palette = [
            'rgba(30, 58, 95, 0.98)',
            'rgba(15, 98, 109, 0.96)',
            'rgba(51, 65, 85, 0.98)',
            'rgba(21, 94, 117, 0.96)',
            'rgba(30, 64, 175, 0.94)',
            'rgba(55, 65, 81, 0.98)',
            'rgba(14, 116, 144, 0.96)',
            'rgba(71, 85, 105, 0.95)',
            'rgba(23, 37, 84, 0.98)',
            'rgba(13, 148, 136, 0.96)',
            'rgba(47, 69, 83, 0.98)',
            'rgba(8, 145, 178, 0.95)',
        ];

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = $palette[$i % count($palette)];
        }

        return $out;
    }

    /**
     * Borde sutil tipo vidrio para cada barra.
     *
     * @return list<string>
     */
    public static function barBorderColors(int $count): array
    {
        $c = 'rgba(255, 255, 255, 0.22)';
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = $c;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function commonOptions(bool $isHorizontal): array
    {
        $axis = $isHorizontal ? 'y' : 'x';

        return [
            'animation' => [
                'duration' => 780,
                'easing' => 'easeOutCubic',
            ],
            'animations' => [
                'colors' => [
                    'duration' => 220,
                    'easing' => 'easeOutQuad',
                ],
                'numbers' => [
                    'duration' => 780,
                    'easing' => 'easeOutCubic',
                ],
            ],
            'datasets' => [
                'bar' => [
                    'categoryPercentage' => 0.76,
                    'barPercentage' => 0.82,
                ],
            ],
            'elements' => [
                'bar' => [
                    'borderWidth' => 1,
                    'borderRadius' => 12,
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
                        'duration' => 160,
                    ],
                    'backgroundColor' => 'rgba(15, 23, 42, 0.88)',
                    'titleColor' => 'rgba(248, 250, 252, 0.98)',
                    'bodyColor' => 'rgba(226, 232, 240, 0.95)',
                    'borderColor' => 'rgba(255, 255, 255, 0.12)',
                    'borderWidth' => 1,
                    'titleFont' => [
                        'size' => 13,
                        'weight' => '600',
                    ],
                    'bodyFont' => [
                        'size' => 12,
                        'weight' => '500',
                    ],
                    'padding' => 14,
                    'cornerRadius' => 14,
                    'displayColors' => true,
                    'boxPadding' => 6,
                    'caretPadding' => 10,
                    'caretSize' => 7,
                    'intersect' => false,
                    'mode' => 'index',
                    'position' => 'nearest',
                ],
            ],
            'transitions' => [
                'active' => [
                    'animation' => [
                        'duration' => 200,
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
     * Rejilla y ejes discretos para tarjetas glass.
     *
     * @return array<string, mixed>
     */
    public static function scaleStyling(): array
    {
        $grid = [
            'display' => true,
            'drawOnChartArea' => true,
            'drawBorder' => true,
            'color' => 'rgba(148, 163, 184, 0.18)',
            'lineWidth' => 1,
        ];

        $border = [
            'display' => true,
            'color' => 'rgba(148, 163, 184, 0.28)',
        ];

        $tickFont = [
            'size' => 10,
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
