<?php

namespace App\Filament\Widgets\Support;

/**
 * Paleta de marca para barras (hub marketing + dashboard principal).
 *
 * Orden: Blizzard Blue → Sunshade (frío a cálido).
 */
final class BrandChartPalette
{
    /**
     * @var list<string>
     */
    private const array HEX = [
        '#90E2ED',
        '#369CBB',
        '#406792',
        '#B46F9C',
        '#D05B61',
        '#FE7B47',
        '#FFA72C',
    ];

    /**
     * @return list<string>
     */
    public static function barFills(int $count): array
    {
        return self::withAlpha($count, 0.82);
    }

    /**
     * @return list<string>
     */
    public static function barHovers(int $count): array
    {
        return self::withAlpha($count, 0.96);
    }

    /**
     * Misma paleta con menor opacidad (p. ej. productos menos vendidos).
     *
     * @return list<string>
     */
    public static function mutedBarFills(int $count): array
    {
        return self::withAlpha($count, 0.52);
    }

    /**
     * @return list<string>
     */
    public static function mutedBarHovers(int $count): array
    {
        return self::withAlpha($count, 0.72);
    }

    /**
     * Borde tipo vidrio sobre fondos glass / iOS.
     *
     * @return list<string>
     */
    public static function barBorderColors(int $count): array
    {
        $c = 'rgba(255, 255, 255, 0.28)';
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = $c;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function withAlpha(int $count, float $alpha): array
    {
        $palette = self::HEX;
        $n = count($palette);
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = self::hexToRgba($palette[$i % $n], $alpha);
        }

        return $out;
    }

    private static function hexToRgba(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $alpha);
    }
}
