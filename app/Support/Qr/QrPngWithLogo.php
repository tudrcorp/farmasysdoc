<?php

namespace App\Support\Qr;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use GdImage;
use RuntimeException;

final class QrPngWithLogo
{
    public function dataUri(string $payload, ?string $logoPath = null, int $size = 240): string
    {
        $logoPath ??= public_path('images/logos/favicon.png');
        $qr = $this->renderQr($payload);

        $this->overlayLogo($qr, $logoPath);

        return $this->toDataUri($qr, $size);
    }

    private function renderQr(string $payload): GdImage
    {
        $options = new QROptions;
        $options->outputType = QROutputInterface::GDIMAGE_PNG;
        $options->returnResource = true;
        $options->eccLevel = EccLevel::H;
        $options->addLogoSpace = true;
        $options->logoSpaceWidth = 13;
        $options->logoSpaceHeight = 13;
        $options->scale = 10;
        $options->quietzoneSize = 2;
        $options->imageTransparent = false;

        $qr = (new QRCode($options))->render($payload);

        if (! $qr instanceof GdImage) {
            throw new RuntimeException('No se pudo generar el código QR.');
        }

        return $qr;
    }

    private function overlayLogo(GdImage $qr, string $logoPath): void
    {
        if (! is_file($logoPath)) {
            return;
        }

        $logo = imagecreatefrompng($logoPath);

        if ($logo === false) {
            return;
        }

        $qrSize = imagesx($qr);
        $box = (int) round($qrSize * 0.24);
        $pad = (int) max(4, round($box * 0.1));
        $inner = $box - ($pad * 2);
        $origin = (int) (($qrSize - $box) / 2);
        $white = imagecolorallocate($qr, 255, 255, 255);

        if ($white !== false) {
            $this->fillRoundedRectangle($qr, $origin, $origin, $box, (int) round($box * 0.22), $white);
        }

        imagealphablending($qr, true);
        imagecopyresampled(
            $qr,
            $logo,
            $origin + $pad,
            $origin + $pad,
            0,
            0,
            $inner,
            $inner,
            imagesx($logo),
            imagesy($logo),
        );
        imagedestroy($logo);
    }

    private function fillRoundedRectangle(GdImage $image, int $x, int $y, int $size, int $radius, int $color): void
    {
        $radius = min($radius, intdiv($size, 2));
        $inner = $size - ($radius * 2);

        imagefilledrectangle($image, $x + $radius, $y, $x + $radius + $inner, $y + $size, $color);
        imagefilledrectangle($image, $x, $y + $radius, $x + $size, $y + $radius + $inner, $color);
        imagefilledellipse($image, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x + $size - $radius, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x + $radius, $y + $size - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x + $size - $radius, $y + $size - $radius, $radius * 2, $radius * 2, $color);
    }

    private function toDataUri(GdImage $image, int $size): string
    {
        $resized = imagescale($image, $size, $size);
        imagedestroy($image);

        if ($resized === false) {
            throw new RuntimeException('No se pudo ajustar el tamaño del código QR.');
        }

        ob_start();
        imagepng($resized);
        $png = (string) ob_get_clean();
        imagedestroy($resized);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
