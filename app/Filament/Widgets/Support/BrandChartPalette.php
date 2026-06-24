<?php

namespace App\Filament\Widgets\Support;

/**
 * Paleta de marca para barras (hub marketing + dashboard principal).
 *
 * Serie por sucursal (dashboard): Boston Blue → Sunshade → Teal Farmadoc.
 * Serie extendida (marketing / muchas categorías): Blizzard Blue → Sunshade.
 */
final class BrandChartPalette
{
    /**
     * Tonos vivos por sucursal (alta saturación, opacidad plena en barras).
     *
     * @var list<string>
     */
    private const array BRANCH_SERIES_VIVID_HEX = [
        '#32C4F0',
        '#FFAD33',
        '#1FD9CC',
    ];

    /**
     * Borde más oscuro por sucursal (contraste y definición sobre fondo oscuro).
     *
     * @var list<string>
     */
    private const array BRANCH_SERIES_BORDER_HEX = [
        '#148FB5',
        '#D97706',
        '#0D9488',
    ];

    /**
     * Hover ligeramente más claro para feedback visual.
     *
     * @var list<string>
     */
    private const array BRANCH_SERIES_HOVER_HEX = [
        '#5DD4F7',
        '#FFC966',
        '#4AE8DC',
    ];

    /**
     * Paleta extendida viva (categorías / múltiples series), misma secuencia que HEX de marca.
     *
     * @var list<string>
     */
    private const array SERIES_VIVID_HEX = [
        '#6EDCF7',
        '#32C4F0',
        '#5B8FD4',
        '#D084B8',
        '#F06B72',
        '#FF8F52',
        '#FFAD33',
    ];

    /**
     * @var list<string>
     */
    private const array SERIES_BORDER_HEX = [
        '#2AABCF',
        '#148FB5',
        '#3D6FA8',
        '#A86294',
        '#C44A52',
        '#D96E2E',
        '#D97706',
    ];

    /**
     * @var list<string>
     */
    private const array SERIES_HOVER_HEX = [
        '#9AE8FA',
        '#5DD4F7',
        '#7AAFE0',
        '#E0A0CC',
        '#FF8A90',
        '#FFB07A',
        '#FFC966',
    ];

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
     * Barras agrupadas por sucursal (máx. 3 tonos de marca distintos).
     *
     * @return list<string>
     */
    public static function branchBarFills(int $count): array
    {
        return self::withAlphaFromPalette(self::BRANCH_SERIES_VIVID_HEX, $count, 1.0);
    }

    /**
     * @return list<string>
     */
    public static function branchBarHovers(int $count): array
    {
        return self::withAlphaFromPalette(self::BRANCH_SERIES_HOVER_HEX, $count, 1.0);
    }

    /**
     * @return list<string>
     */
    public static function branchBarBorderColors(int $count): array
    {
        return self::withAlphaFromPalette(self::BRANCH_SERIES_BORDER_HEX, $count, 1.0);
    }

    /**
     * Barras por categoría o series múltiples (paleta extendida de marca).
     *
     * @return list<string>
     */
    public static function seriesBarFills(int $count): array
    {
        return self::withAlphaFromPalette(self::SERIES_VIVID_HEX, $count, 1.0);
    }

    /**
     * @return list<string>
     */
    public static function seriesBarHovers(int $count): array
    {
        return self::withAlphaFromPalette(self::SERIES_HOVER_HEX, $count, 1.0);
    }

    /**
     * @return list<string>
     */
    public static function seriesBarBorderColors(int $count): array
    {
        return self::withAlphaFromPalette(self::SERIES_BORDER_HEX, $count, 1.0);
    }

    /**
     * @return list<string>
     */
    public static function barFills(int $count): array
    {
        return self::withAlphaFromPalette(self::HEX, $count, 0.82);
    }

    /**
     * @return list<string>
     */
    public static function barHovers(int $count): array
    {
        return self::withAlphaFromPalette(self::HEX, $count, 0.96);
    }

    /**
     * Misma paleta con menor opacidad (p. ej. productos menos vendidos).
     *
     * @return list<string>
     */
    public static function mutedBarFills(int $count): array
    {
        return self::withAlphaFromPalette(self::HEX, $count, 0.52);
    }

    /**
     * @return list<string>
     */
    public static function mutedBarHovers(int $count): array
    {
        return self::withAlphaFromPalette(self::HEX, $count, 0.72);
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
     * @param  list<string>  $palette
     * @return list<string>
     */
    private static function withAlphaFromPalette(array $palette, int $count, float $alpha): array
    {
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
