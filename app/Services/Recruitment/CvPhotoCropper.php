<?php

namespace App\Services\Recruitment;

use App\Models\RecruitmentCandidate;
use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Recorta la foto del candidato según la ubicación indicada por Gemini.
 * Devuelve null si el CV no tiene foto y lanza una excepción si la tiene pero no se pudo extraer.
 */
class CvPhotoCropper
{
    private const int MINIMUM_SIDE_PIXELS = 48;

    private const float BOX_PADDING_RATIO = 0.04;

    /**
     * El proceso web no siempre hereda el PATH de la terminal (Herd no incluye Homebrew).
     *
     * @var list<string>
     */
    private const array EXTRA_BINARY_DIRECTORIES = ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin', '/bin'];

    /**
     * @param  array<string, mixed>|null  $photo  page (desde 1) y box_2d [ymin, xmin, ymax, xmax] en escala 0-1000
     * @return string|null Ruta en el disco público
     */
    public function crop(string $absolutePath, string $mimeType, ?array $photo): ?string
    {
        $box = $photo['box_2d'] ?? null;
        if (! is_array($box) || count($box) !== 4) {
            return null;
        }

        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('El servidor no tiene la extensión GD para recortar imágenes.');
        }

        $renderedPage = $mimeType === 'application/pdf'
            ? $this->renderPdfPage($absolutePath, max(1, (int) ($photo['page'] ?? 1)))
            : null;

        try {
            return $this->storeCrop(
                $this->loadImage($renderedPage ?? $absolutePath),
                array_map('intval', array_values($box)),
            );
        } finally {
            if ($renderedPage !== null) {
                File::delete($renderedPage);
            }
        }
    }

    private function loadImage(string $path): GdImage
    {
        $contents = @file_get_contents($path);
        $image = $contents !== false ? @imagecreatefromstring($contents) : false;

        if (! $image instanceof GdImage) {
            throw new RuntimeException('No se pudo abrir la imagen del CV.');
        }

        return $this->applyExifOrientation($image, $path);
    }

    /**
     * Las fotos tomadas con el teléfono guardan la rotación en EXIF y Gemini las ve ya giradas.
     */
    private function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    /**
     * @param  array{0: int, 1: int, 2: int, 3: int}  $box
     */
    private function storeCrop(GdImage $image, array $box): string
    {
        [$yMin, $xMin, $yMax, $xMax] = $box;
        $width = imagesx($image);
        $height = imagesy($image);

        $horizontalPadding = (int) round(($xMax - $xMin) * self::BOX_PADDING_RATIO);
        $verticalPadding = (int) round(($yMax - $yMin) * self::BOX_PADDING_RATIO);

        $left = (int) round(max(0, $xMin - $horizontalPadding) / 1000 * $width);
        $top = (int) round(max(0, $yMin - $verticalPadding) / 1000 * $height);
        $cropWidth = (int) round(min(1000, $xMax + $horizontalPadding) / 1000 * $width) - $left;
        $cropHeight = (int) round(min(1000, $yMax + $verticalPadding) / 1000 * $height) - $top;

        if ($cropWidth < self::MINIMUM_SIDE_PIXELS || $cropHeight < self::MINIMUM_SIDE_PIXELS) {
            throw new RuntimeException('La foto detectada en el CV es demasiado pequeña.');
        }

        $cropped = imagecrop($image, ['x' => $left, 'y' => $top, 'width' => $cropWidth, 'height' => $cropHeight]);
        if ($cropped === false) {
            throw new RuntimeException('No se pudo recortar la foto del CV.');
        }

        ob_start();
        imagejpeg($cropped, null, 90);
        $jpeg = (string) ob_get_clean();

        $path = 'recruitment/photos/'.Str::uuid().'.jpg';

        if (! Storage::disk(RecruitmentCandidate::PHOTO_DISK)->put($path, $jpeg)) {
            throw new RuntimeException('No se pudo guardar la foto del CV.');
        }

        return $path;
    }

    private function renderPdfPage(string $absolutePath, int $page): string
    {
        $binary = $this->pdfRendererBinary();
        $prefix = Storage::disk(RecruitmentCandidate::CV_DISK)->path('recruitment/tmp/'.Str::uuid());
        File::ensureDirectoryExists(dirname($prefix));

        $result = Process::timeout(30)->run([
            $binary,
            '-f', (string) $page,
            '-l', (string) $page,
            '-r', '200',
            '-png',
            '-singlefile',
            $absolutePath,
            $prefix,
        ]);

        $output = "{$prefix}.png";

        if (! $result->successful() || ! is_file($output)) {
            throw new RuntimeException('pdftoppm no pudo convertir el PDF: '.Str::limit(trim($result->errorOutput()), 200));
        }

        return $output;
    }

    private function pdfRendererBinary(): string
    {
        $configured = (string) config('recruitment.pdftoppm_binary');

        $binary = is_executable($configured)
            ? $configured
            : (new ExecutableFinder)->find(basename($configured), null, self::EXTRA_BINARY_DIRECTORIES);

        if ($binary === null) {
            throw new RuntimeException('Falta instalar pdftoppm (poppler-utils) en el servidor.');
        }

        return $binary;
    }
}
