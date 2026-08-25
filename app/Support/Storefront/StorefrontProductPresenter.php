<?php

namespace App\Support\Storefront;

use App\Models\Product;
use App\Services\Products\CatalogImageOptimizer;

final class StorefrontProductPresenter
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     barcode: string,
     *     brand: string,
     *     presentation: string,
     *     active_ingredient: string,
     *     sale_price: float,
     *     effective_price: float,
     *     discount_percent: float,
     *     applies_vat: bool,
     *     requires_prescription: bool,
     *     stock_available: float,
     *     image_url: string,
     *     category: string|null
     * }
     */
    public static function fromProduct(Product $product): array
    {
        $listPrice = round((float) ($product->sale_price ?? 0), 2);
        $discount = max(0.0, min(100.0, (float) ($product->discount_percent ?? 0)));
        $ingredient = $product->active_ingredient;

        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'barcode' => filled($product->barcode) ? (string) $product->barcode : '—',
            'brand' => filled($product->brand) ? (string) $product->brand : '—',
            'presentation' => filled($product->presentation_type) ? (string) $product->presentation_type : '—',
            'active_ingredient' => self::formatActiveIngredient($ingredient),
            'sale_price' => $listPrice,
            'effective_price' => $product->effectiveSaleUnitPrice(),
            'discount_percent' => round($discount, 2),
            'applies_vat' => (bool) $product->applies_vat,
            'requires_prescription' => (bool) $product->requires_prescription,
            'stock_available' => round((float) ($product->getAttribute('stock_available') ?? 0), 3),
            'image_url' => self::imageUrl($product->image, (string) $product->name),
            'category' => $product->productCategory?->name,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     barcode: string,
     *     brand: string,
     *     presentation: string,
     *     active_ingredient: string,
     *     sale_price: float,
     *     effective_price: float,
     *     discount_percent: float,
     *     applies_vat: bool,
     *     requires_prescription: bool,
     *     stock_available: float,
     *     image_url: string,
     *     category: string|null
     * }
     */
    public static function fromSearchRow(object $row): array
    {
        $listPrice = round((float) ($row->sale_price ?? 0), 2);
        $discount = max(0.0, min(100.0, (float) ($row->discount_percent ?? 0)));
        $name = (string) ($row->name ?? 'Producto');

        return [
            'id' => (int) $row->id,
            'name' => $name,
            'barcode' => filled($row->barcode ?? null) ? (string) $row->barcode : '—',
            'brand' => filled($row->brand ?? null) ? (string) $row->brand : '—',
            'presentation' => filled($row->presentation_type ?? null) ? (string) $row->presentation_type : '—',
            'active_ingredient' => self::formatActiveIngredient($row->active_ingredient ?? null),
            'sale_price' => $listPrice,
            'effective_price' => round($listPrice * (1 - ($discount / 100)), 2),
            'discount_percent' => round($discount, 2),
            'applies_vat' => (bool) ($row->applies_vat ?? false),
            'requires_prescription' => (bool) ($row->requires_prescription ?? false),
            'stock_available' => round((float) ($row->stock_available ?? 0), 3),
            'image_url' => self::imageUrl(isset($row->image) ? (string) $row->image : null, $name),
            'category' => filled($row->category_name ?? null) ? (string) $row->category_name : null,
        ];
    }

    public static function formatActiveIngredient(mixed $value): string
    {
        if (is_array($value)) {
            $items = array_values(array_filter(array_map(
                static fn (mixed $item): string => is_string($item) ? trim($item) : '',
                $value,
            )));

            return $items !== [] ? implode(', ', $items) : '—';
        }

        if (is_string($value) && trim($value) !== '') {
            $text = trim($value);

            if (str_starts_with($text, '[') && str_ends_with($text, ']')) {
                $decoded = json_decode($text, true);

                if (is_array($decoded)) {
                    return self::formatActiveIngredient($decoded);
                }

                $compact = trim(str_replace('"', '', substr($text, 1, -1)));

                return $compact === '' ? '—' : $compact;
            }

            return $text;
        }

        return '—';
    }

    public static function imageUrl(?string $path, string $name): string
    {
        $url = CatalogImageOptimizer::url($path);

        if (is_string($url) && $url !== '') {
            return $url;
        }

        return self::placeholderSvg($name);
    }

    public static function placeholderSvg(string $name): string
    {
        $initials = htmlspecialchars(Product::initialsFromCommercialName($name), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="640" height="640" viewBox="0 0 640 640">
                <defs>
                    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#E7F7F8"/>
                        <stop offset="100%" stop-color="#18ACB2"/>
                    </linearGradient>
                </defs>
                <rect width="640" height="640" rx="140" fill="url(#g)"/>
                <circle cx="320" cy="320" r="168" fill="#ffffff" fill-opacity="0.42"/>
                <text x="320" y="338" text-anchor="middle" font-family="ui-sans-serif,system-ui,sans-serif" font-size="132" font-weight="700" fill="#0E949A">{$initials}</text>
            </svg>
        SVG;

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }
}
