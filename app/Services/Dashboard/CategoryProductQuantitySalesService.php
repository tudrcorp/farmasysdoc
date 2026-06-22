<?php

namespace App\Services\Dashboard;

use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleItem;
use App\Support\Filament\DashboardBranchFilter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Unidades vendidas (suma de cantidades en ítems) agrupadas por categoría o por producto dentro de una categoría.
 */
final class CategoryProductQuantitySalesService
{
    /**
     * @return array{
     *     labels: list<string>,
     *     data: list<float>,
     *     category_ids: list<int>,
     *     total_quantity: float,
     * }
     */
    public function totalsByCategory(?CarbonInterface $from, ?CarbonInterface $to, int $limit = 20): array
    {
        $rows = $this->baseSaleItemsQuery($from, $to)
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->selectRaw('COALESCE(products.product_category_id, 0) as category_id, SUM(sale_items.quantity) as total_quantity')
            ->groupBy('category_id')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'labels' => [],
                'data' => [],
                'category_ids' => [],
                'total_quantity' => 0.0,
            ];
        }

        $categoryIds = $rows
            ->pluck('category_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $categoryNames = $categoryIds !== []
            ? ProductCategory::query()->whereIn('id', $categoryIds)->pluck('name', 'id')->all()
            : [];

        $labels = [];
        $data = [];
        $orderedCategoryIds = [];
        $totalQuantity = 0.0;

        foreach ($rows as $row) {
            $categoryId = (int) $row->category_id;
            $quantity = round((float) $row->total_quantity, 3);

            $name = $categoryId === 0
                ? __('Sin categoría')
                : ($categoryNames[$categoryId] ?? __('Categoría #:id', ['id' => $categoryId]));

            $labels[] = Str::limit((string) $name, 34, '…');
            $data[] = $quantity;
            $orderedCategoryIds[] = $categoryId;
            $totalQuantity += $quantity;
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'category_ids' => $orderedCategoryIds,
            'total_quantity' => round($totalQuantity, 3),
        ];
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     data: list<float>,
     *     category_name: string,
     *     total_quantity: float,
     * }
     */
    public function totalsByProductInCategory(int $categoryId, ?CarbonInterface $from, ?CarbonInterface $to, int $limit = 25): array
    {
        $query = $this->baseSaleItemsQuery($from, $to)
            ->join('products', 'products.id', '=', 'sale_items.product_id');

        if ($categoryId === 0) {
            $query->whereNull('products.product_category_id');
        } else {
            $query->where('products.product_category_id', $categoryId);
        }

        $rows = $query
            ->selectRaw('sale_items.product_id, SUM(sale_items.quantity) as total_quantity')
            ->groupBy('sale_items.product_id')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        $categoryName = $this->resolveCategoryName($categoryId);

        if ($rows->isEmpty()) {
            return [
                'labels' => [],
                'data' => [],
                'category_name' => $categoryName,
                'total_quantity' => 0.0,
            ];
        }

        $productIds = $rows
            ->pluck('product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $productNames = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('name', 'id')
            ->all();

        $labels = [];
        $data = [];
        $totalQuantity = 0.0;

        foreach ($rows as $row) {
            $productId = (int) $row->product_id;
            $quantity = round((float) $row->total_quantity, 3);

            $labels[] = Str::limit((string) ($productNames[$productId] ?? __('Producto #:id', ['id' => $productId])), 34, '…');
            $data[] = $quantity;
            $totalQuantity += $quantity;
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'category_name' => $categoryName,
            'total_quantity' => round($totalQuantity, 3),
        ];
    }

    private function resolveCategoryName(int $categoryId): string
    {
        if ($categoryId === 0) {
            return (string) __('Sin categoría');
        }

        $name = ProductCategory::query()->whereKey($categoryId)->value('name');

        return filled($name)
            ? (string) $name
            : (string) __('Categoría #:id', ['id' => $categoryId]);
    }

    /**
     * @return Builder<SaleItem>
     */
    private function baseSaleItemsQuery(?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        return SaleItem::query()
            ->whereNotNull('sale_items.product_id')
            ->whereHas('sale', function (Builder $sale) use ($from, $to): void {
                $sale->where('status', SaleStatus::Completed)
                    ->whereNotNull('sold_at');

                DashboardBranchFilter::applyToSalesQuery($sale);

                if ($from instanceof CarbonInterface && $to instanceof CarbonInterface) {
                    $sale->whereBetween('sold_at', [$from, $to]);
                }
            });
    }
}
