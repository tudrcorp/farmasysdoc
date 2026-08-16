<?php

namespace App\Services\Hr;

use Illuminate\Validation\ValidationException;

final class EmployeeFileBackgroundRemover
{
    public const SIGNATURE = 'signature';

    public const FINGERPRINT = 'fingerprint';

    public function toTransparentPng(string $binary, string $mode = self::SIGNATURE): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages([
                'image' => 'El servidor no puede limpiar la imagen. Inténtalo de nuevo.',
            ]);
        }

        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            throw ValidationException::withMessages([
                'image' => 'No se pudo leer la imagen.',
            ]);
        }

        [$source, $width, $height] = $this->fitWithin($source, 1600);

        imagealphablending($source, false);
        imagesavealpha($source, true);

        $png = imagecreatetruecolor($width, $height);
        imagealphablending($png, false);
        imagesavealpha($png, true);
        imagefilledrectangle($png, 0, 0, $width, $height, imagecolorallocatealpha($png, 0, 0, 0, 127));

        $isSignature = $mode === self::ModeSignature;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($source, $x, $y);
                $existingAlpha = ($rgba & 0x7F000000) >> 24;
                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;
                $alpha = max($existingAlpha, $this->transparencyFor($red, $green, $blue, $isSignature));
                imagesetpixel($png, $x, $y, imagecolorallocatealpha($png, $red, $green, $blue, $alpha));
            }
        }

        ob_start();
        imagepng($png, null, 6);
        $output = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($png);

        if ($output === '' || strlen($output) > EmployeeFileMediaStore::MAX_BYTES) {
            throw ValidationException::withMessages([
                'image' => 'La imagen quedó demasiado grande después de limpiarla.',
            ]);
        }

        return $output;
    }

    /**
     * @return array{0: \GdImage, 1: int, 2: int}
     */
    private function fitWithin(\GdImage $source, int $maxEdge): array
    {
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= $maxEdge && $height <= $maxEdge) {
            return [$source, $width, $height];
        }

        $scale = $maxEdge / max($width, $height);
        $fittedWidth = max(1, (int) round($width * $scale));
        $fittedHeight = max(1, (int) round($height * $scale));
        $fitted = imagecreatetruecolor($fittedWidth, $fittedHeight);
        imagealphablending($fitted, false);
        imagesavealpha($fitted, true);
        imagecopyresampled($fitted, $source, 0, 0, 0, 0, $fittedWidth, $fittedHeight, $width, $height);
        imagedestroy($source);

        return [$fitted, $fittedWidth, $fittedHeight];
    }

    private function transparencyFor(int $red, int $green, int $blue, bool $isSignature): int
    {
        $luma = (0.299 * $red) + (0.587 * $green) + (0.114 * $blue);
        $spread = max($red, $green, $blue) - min($red, $green, $blue);

        if ($isSignature) {
            if ($luma >= 246) {
                return 127;
            }

            if ($luma <= 36) {
                return 0;
            }

            return (int) round((($luma - 36) / 210) * 127);
        }

        if ($luma < 228 || $spread > 22) {
            return 0;
        }

        if ($luma >= 248) {
            return 127;
        }

        return (int) round((($luma - 228) / 20) * 127);
    }
}
