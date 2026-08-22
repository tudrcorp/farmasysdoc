<?php

namespace App\Support\Storefront;

use App\Enums\SaleStatus;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final class StorefrontCatalog
{
    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     image_url: string|null,
     *     product_count: int,
     *     is_medication: bool
     * }>
     */
    public function categories(): array
    {
        return ProductCategory::query()
            ->where('is_active', true)
            ->withCount(['products as product_count' => function (Builder $query): void {
                $query->where('is_active', true);
            }])
            ->orderByDesc('product_count')
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(function (ProductCategory $category): array {
                $image = filled($category->image)
                    ? Storage::disk('public')->url((string) $category->image)
                    : null;

                return [
                    'id' => (int) $category->id,
                    'name' => (string) $category->name,
                    'slug' => (string) ($category->slug ?: ''),
                    'image_url' => $image,
                    'product_count' => (int) ($category->product_count ?? 0),
                    'is_medication' => (bool) $category->is_medication,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bestsellers(): array
    {
        $rankedIds = $this->bestsellerProductIds(16);

        if ($rankedIds === []) {
            return $this->presentProducts(
                $this->inStockQuery()
                    ->orderByDesc('products.discount_percent')
                    ->orderBy('products.name')
                    ->limit(16)
                    ->get(),
            );
        }

        return $this->presentProducts(
            $this->inStockQuery()
                ->whereIn('products.id', $rankedIds)
                ->orderByRaw($this->caseOrderSql($rankedIds), $rankedIds)
                ->limit(16)
                ->get(),
        ) ?: $this->presentProducts(
            $this->inStockQuery()
                ->orderByDesc('products.discount_percent')
                ->orderBy('products.name')
                ->limit(16)
                ->get(),
        );
    }

    /**
     * @param  list<int>  $excludeIds
     * @return list<array<string, mixed>>
     */
    public function recommended(array $excludeIds = []): array
    {
        $query = $this->inStockQuery()
            ->orderBy('products.name')
            ->limit(16);

        if ($excludeIds !== []) {
            $query->whereNotIn('products.id', $excludeIds);
        }

        return $this->presentProducts($query->get());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function offers(): array
    {
        return $this->presentProducts(
            $this->inStockQuery()
                ->where('products.discount_percent', '>', 0)
                ->orderByDesc('products.discount_percent')
                ->orderBy('products.name')
                ->limit(16)
                ->get(),
        );
    }

    /**
     * @return Builder<Product>
     */
    private function inStockQuery(): Builder
    {
        $stockSub = Inventory::query()
            ->select('product_id')
            ->selectRaw('COALESCE(SUM(quantity - reserved_quantity), 0) as stock_available')
            ->groupBy('product_id');

        return Product::query()
            ->select('products.*')
            ->selectRaw('stock.stock_available as stock_available')
            ->joinSub($stockSub, 'stock', 'stock.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->where('stock.stock_available', '>', 1)
            ->with(['productCategory:id,name,slug']);
    }

    /**
     * @return list<int>
     */
    private function bestsellerProductIds(int $limit): array
    {
        return SaleItem::query()
            ->select('sale_items.product_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', SaleStatus::Completed)
            ->whereNotNull('sale_items.product_id')
            ->groupBy('sale_items.product_id')
            ->orderByRaw('SUM(sale_items.quantity) DESC')
            ->limit($limit)
            ->pluck('product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $ids
     */
    private function caseOrderSql(array $ids): string
    {
        $whens = [];

        foreach (array_values($ids) as $index => $id) {
            $whens[] = 'WHEN ? THEN '.$index;
        }

        return 'CASE products.id '.implode(' ', $whens).' ELSE 99 END';
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    private function presentProducts(Collection $products): array
    {
        return $products
            ->map(fn (Product $product): array => StorefrontProductPresenter::fromProduct($product))
            ->values()
            ->all();
    }
}
