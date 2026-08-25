<?php

namespace App\Support\Products;

use App\Services\Products\CatalogImageOptimizer;
use Filament\Forms\Components\FileUpload;

final class CatalogImageFileUpload
{
    public static function make(string $name, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->disk('public')
            ->directory($directory)
            ->visibility('public')
            ->maxSize(4096)
            ->automaticallyResizeImagesMode('contain')
            ->automaticallyResizeImagesToWidth((string) CatalogImageOptimizer::MAX_EDGE)
            ->automaticallyResizeImagesToHeight((string) CatalogImageOptimizer::MAX_EDGE)
            ->automaticallyUpscaleImagesWhenResizing(false)
            ->imageEditor()
            ->panelLayout('integrated')
            ->columnSpanFull();
    }
}
