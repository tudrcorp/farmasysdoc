<?php

namespace App\Services\Products;

use App\Models\Product;
use App\Models\ProductCategory;
use GdImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class CatalogImageOptimizer
{
    public const MAX_EDGE = 800;

    public const QUALITY = 75;

    public const PRODUCTS = 'products';

    public const CATEGORIES = 'product-categories';

    /**
     * @var list<string>
     */
    public const DIRECTORIES = [
        self::PRODUCTS,
        self::CATEGORIES,
    ];

    private const HASH_LENGTH = 40;

    private const MAX_PIXELS = 40_000_000;

    public function applyTo(Model $model, string $attribute, string $directory, bool $force = false): void
    {
        $path = $model->getAttribute($attribute);

        if (! is_string($path) || trim($path) === '') {
            $this->deleteOrphan($model->getOriginal($attribute), $model);
            $model->setAttribute($attribute, null);

            return;
        }

        $optimized = $this->optimize($path, $directory, $force);
        $model->setAttribute($attribute, $optimized);

        $previous = $model->getOriginal($attribute);

        if (is_string($previous) && $previous !== '' && $previous !== $optimized) {
            $this->deleteOrphan($previous, $model);
        }
    }

    public function optimize(string $path, string $directory, bool $force = false): string
    {
        $path = str_replace('\\', '/', ltrim($path, '/'));

        if (! $force && $this->isOptimized($path, $directory)) {
            return $path;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            Log::warning('Catalog image missing on public disk; left unchanged.', [
                'path' => $path,
            ]);

            return $path;
        }

        $binary = $disk->get($path);

        if (! is_string($binary) || $binary === '') {
            return $path;
        }

        $webp = $this->encodeWebp($binary);

        if ($webp === null) {
            Log::warning('Catalog image could not be converted to WebP; original file kept.', [
                'path' => $path,
            ]);

            return $path;
        }

        $newPath = $directory.'/'.substr(hash('sha256', $webp), 0, self::HASH_LENGTH).'.webp';

        if (! $disk->exists($newPath) && ! $disk->put($newPath, $webp, 'public')) {
            Log::warning('Catalog image WebP could not be stored; original file kept.', [
                'path' => $path,
                'new_path' => $newPath,
            ]);

            return $path;
        }

        if (! $disk->exists($newPath)) {
            return $path;
        }

        if ($path !== $newPath) {
            $disk->delete($path);
        }

        return $newPath;
    }

    public function isOptimized(string $path, string $directory): bool
    {
        $quoted = preg_quote($directory, '#');

        return (bool) preg_match('#^'.$quoted.'/[a-f0-9]{'.self::HASH_LENGTH.'}\.webp$#', $path);
    }

    public static function url(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', ltrim($path, '/'));
        $directory = dirname($normalized);
        $filename = basename($normalized);

        if (in_array($directory, self::DIRECTORIES, true) && $filename !== '' && $filename !== '.') {
            return route('shop.catalog-media', [
                'directory' => $directory,
                'filename' => $filename,
            ]);
        }

        return Storage::disk('public')->url($normalized);
    }

    private function encodeWebp(string $binary): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }

        $source = @imagecreatefromstring($binary);

        if (! $source instanceof GdImage) {
            return null;
        }

        $source = $this->orientFromExif($source, $binary);

        $width = imagesx($source);
        $height = imagesy($source);

        if (($width * $height) > self::MAX_PIXELS) {
            imagedestroy($source);

            return null;
        }

        [$canvas] = $this->fitWithin($source, self::MAX_EDGE);

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        ob_start();
        $ok = imagewebp($canvas, null, self::QUALITY);
        $output = (string) ob_get_clean();
        imagedestroy($canvas);

        if ($ok === false || $output === '') {
            return null;
        }

        return $output;
    }

    private function orientFromExif(GdImage $image, string $binary): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($binary));

        if (! is_array($exif)) {
            return $image;
        }

        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if (! $rotated instanceof GdImage) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    /**
     * @return array{0: GdImage, 1: int, 2: int}
     */
    private function fitWithin(GdImage $source, int $maxEdge): array
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
        $transparent = imagecolorallocatealpha($fitted, 0, 0, 0, 127);
        imagefilledrectangle($fitted, 0, 0, $fittedWidth, $fittedHeight, $transparent);
        imagealphablending($fitted, true);
        imagecopyresampled($fitted, $source, 0, 0, 0, 0, $fittedWidth, $fittedHeight, $width, $height);
        imagedestroy($source);

        return [$fitted, $fittedWidth, $fittedHeight];
    }

    private function deleteOrphan(mixed $path, Model $except): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $usedByProduct = Product::query()
            ->where('image', $path)
            ->when(
                $except instanceof Product && $except->exists,
                fn ($query) => $query->whereKeyNot($except->getKey()),
            )
            ->exists();

        $usedByCategory = ProductCategory::query()
            ->where('image', $path)
            ->when(
                $except instanceof ProductCategory && $except->exists,
                fn ($query) => $query->whereKeyNot($except->getKey()),
            )
            ->exists();

        if ($usedByProduct || $usedByCategory) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
