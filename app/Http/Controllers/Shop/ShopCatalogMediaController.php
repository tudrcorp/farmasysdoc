<?php

namespace App\Http\Controllers\Shop;

use App\Services\Products\CatalogImageOptimizer;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ShopCatalogMediaController
{
    public function __invoke(string $directory, string $filename): BinaryFileResponse
    {
        abort_unless(in_array($directory, CatalogImageOptimizer::DIRECTORIES, true), 404);

        $filename = basename($filename);

        abort_if($filename === '' || $filename === '.' || str_contains($filename, '..'), 404);

        $path = $directory.'/'.$filename;
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'webp' => 'image/webp',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => null,
        };

        $headers = [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ];

        if ($mime !== null) {
            $headers['Content-Type'] = $mime;
        }

        return response()->file($disk->path($path), $headers);
    }
}
