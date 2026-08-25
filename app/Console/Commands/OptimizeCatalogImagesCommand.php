<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Products\CatalogImageOptimizer;
use App\Support\Shop\ShopCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

#[Signature('catalog:optimize-images {--force : Regenera incluso las fotos que ya están en WebP de catálogo} {--chunk=100 : Tamaño de lote}')]
#[Description('Convierte fotos de productos y categorías a WebP 800 px para la PWA')]
final class OptimizeCatalogImagesCommand extends Command
{
    public function handle(CatalogImageOptimizer $optimizer): int
    {
        if (! function_exists('imagewebp')) {
            $this->error('PHP GD no tiene soporte WebP en este servidor.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $chunk = max(1, (int) $this->option('chunk'));
        $converted = 0;
        $skipped = 0;

        $this->info('Optimizando imágenes de productos…');

        Product::query()
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->orderBy('id')
            ->chunkById($chunk, function ($products) use ($optimizer, $force, &$converted, &$skipped): void {
                foreach ($products as $product) {
                    if (! $product instanceof Product) {
                        continue;
                    }

                    $this->process($product, CatalogImageOptimizer::PRODUCTS, $optimizer, $force, $converted, $skipped);
                }
            });

        $this->info('Optimizando imágenes de categorías…');

        ProductCategory::query()
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->orderBy('id')
            ->chunkById($chunk, function ($categories) use ($optimizer, $force, &$converted, &$skipped): void {
                foreach ($categories as $category) {
                    if (! $category instanceof ProductCategory) {
                        continue;
                    }

                    $this->process($category, CatalogImageOptimizer::CATEGORIES, $optimizer, $force, $converted, $skipped);
                }
            });

        ShopCatalog::bump();

        $this->info("Listo. Convertidas: {$converted}. Sin cambios: {$skipped}.");

        return self::SUCCESS;
    }

    private function process(
        Model $model,
        string $directory,
        CatalogImageOptimizer $optimizer,
        bool $force,
        int &$converted,
        int &$skipped,
    ): void {
        $original = (string) $model->getAttribute('image');

        if (! $force && $optimizer->isOptimized($original, $directory)) {
            $skipped++;

            return;
        }

        $optimizer->applyTo($model, 'image', $directory, $force);

        if ((string) $model->getAttribute('image') === $original) {
            $skipped++;

            return;
        }

        $model->saveQuietly();
        $converted++;
    }
}
