<?php

namespace App\Services\Fiscal;

use RuntimeException;

final class ThermalFiscalReceiptImageGenerator
{
    public function generateJpeg(string $plainText): string
    {
        $fontPath = (string) config('fiscal.thermal_receipt_font');
        $fontSize = max(10, (int) config('fiscal.whatsapp_image_font_size', 14));
        $padding = max(8, (int) config('fiscal.whatsapp_image_padding', 18));
        $lineHeight = (int) round($fontSize * 1.35);

        if (! is_readable($fontPath)) {
            throw new RuntimeException('No se encontró la fuente monoespaciada para generar la imagen de la factura.');
        }

        $lines = preg_split('/\r\n|\r|\n/', rtrim($plainText, "\n\r")) ?: [''];
        $thermalWidth = max(24, min(48, (int) config('fiscal.thermal_line_width', 42)));
        $referenceLine = str_repeat('M', $thermalWidth);
        $referenceBox = imagettfbbox($fontSize, 0, $fontPath, $referenceLine);
        $targetPixelWidth = $referenceBox !== false
            ? (int) abs($referenceBox[2] - $referenceBox[0])
            : 0;

        $maxWidth = 0;
        foreach ($lines as $line) {
            $box = imagettfbbox($fontSize, 0, $fontPath, $line !== '' ? $line : ' ');
            if ($box === false) {
                continue;
            }

            $maxWidth = max($maxWidth, (int) abs($box[2] - $box[0]));
        }

        $imageWidth = max(280, ($targetPixelWidth > 0 ? $targetPixelWidth : $maxWidth) + ($padding * 2));
        $imageHeight = max(120, (count($lines) * $lineHeight) + ($padding * 2));

        $image = imagecreatetruecolor($imageWidth, $imageHeight);
        if ($image === false) {
            throw new RuntimeException('No se pudo crear la imagen de la factura.');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 17, 17, 17);
        imagefill($image, 0, 0, $white);

        $y = $padding + $fontSize;
        foreach ($lines as $line) {
            imagettftext($image, $fontSize, 0, $padding, $y, $black, $fontPath, $line);
            $y += $lineHeight;
        }

        ob_start();
        imagejpeg($image, null, 92);
        $jpeg = ob_get_clean() ?: '';
        imagedestroy($image);

        if ($jpeg === '') {
            throw new RuntimeException('No se pudo codificar la imagen JPEG de la factura.');
        }

        return $jpeg;
    }
}
