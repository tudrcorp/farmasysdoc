<?php

namespace App\Support\Branding;

/**
 * Dale visual identity (copied from the dale platform): logo, Poppins and the brand palette.
 * Used by the partner panel «dale · Aliados».
 *
 * The palettes are hand-tuned OKLCH scales instead of Filament's Color::hex() generator:
 * - primary keeps the brand teal #0BA2AF exactly at 500; 600 (button background) is a touch
 *   darker so white button text reaches WCAG AA contrast.
 * - gray keeps the bluish hue of #C8D2D9 (exactly at 300); the generator would treat such a low
 *   chroma as pure gray and its darkest shade is too light for dark mode.
 */
class DaleBrand
{
    public const LOGO = 'images/dale/logo-dale.png';

    public const FAVICON = 'images/dale/favicon-dale.png';

    public const FONT = 'Poppins';

    public const TEAL = '#0BA2AF';

    public const MIST = '#C8D2D9';

    /**
     * @return array<int, string>
     */
    public static function primary(): array
    {
        return self::scale(205.027, [
            50 => [0.984, 0.014],
            100 => [0.956, 0.034],
            200 => [0.910, 0.060],
            300 => [0.846, 0.086],
            400 => [0.752, 0.106],
            500 => [0.651, 0.110],
            600 => [0.548, 0.094],
            700 => [0.468, 0.080],
            800 => [0.398, 0.066],
            900 => [0.338, 0.054],
            950 => [0.240, 0.040],
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function gray(): array
    {
        return self::scale(238.041, [
            50 => [0.984, 0.003],
            100 => [0.962, 0.006],
            200 => [0.920, 0.010],
            300 => [0.858, 0.015],
            400 => [0.711, 0.020],
            500 => [0.556, 0.022],
            600 => [0.446, 0.022],
            700 => [0.372, 0.020],
            800 => [0.279, 0.017],
            900 => [0.208, 0.014],
            950 => [0.130, 0.011],
        ]);
    }

    /**
     * @param  array<int, array{float, float}>  $shades  shade => [lightness, chroma]
     * @return array<int, string>
     */
    private static function scale(float $hue, array $shades): array
    {
        return array_map(fn (array $shade): string => "oklch({$shade[0]} {$shade[1]} {$hue})", $shades);
    }
}
