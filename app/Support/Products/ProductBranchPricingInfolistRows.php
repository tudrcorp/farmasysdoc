<?php

namespace App\Support\Products;

use App\Models\Inventory;
use App\Models\Product;
use App\Services\Pricing\BranchCategoryProfitResolver;

final class ProductBranchPricingInfolistRows
{
    /**
     * Filas de costos y precios del producto por sucursal (snapshot de inventario + margen aplicado).
     *
     * @return list<array{
     *     branch_name: string,
     *     cost_price: float,
     *     profit_percentage: float,
     *     final_price_without_vat: float,
     *     final_price_with_vat: float,
     *     quantity: float
     * }>
     */
    public static function forProduct(Product $product): array
    {
        $product->loadMissing('productCategory');

        $categoryId = $product->product_category_id !== null
            ? (int) $product->product_category_id
            : null;

        $resolver = app(BranchCategoryProfitResolver::class);

        return Inventory::query()
            ->where('inventories.product_id', $product->id)
            ->join('branches', 'branches.id', '=', 'inventories.branch_id')
            ->where('branches.is_active', true)
            ->orderBy('branches.name')
            ->select('inventories.*')
            ->with(['branch:id,name'])
            ->get()
            ->map(function (Inventory $inventory) use ($categoryId, $resolver): array {
                $branchId = (int) ($inventory->branch_id ?? 0);
                $profitPercentage = $categoryId !== null && $categoryId > 0 && $branchId > 0
                    ? $resolver->resolve($branchId, $categoryId)
                    : 0.0;

                return [
                    'branch_name' => (string) ($inventory->branch?->name ?? ('Sucursal #'.$branchId)),
                    'cost_price' => round(max(0.0, (float) ($inventory->cost_price ?? 0)), 2),
                    'profit_percentage' => round($profitPercentage, 4),
                    'final_price_without_vat' => round(max(0.0, (float) ($inventory->final_price_without_vat ?? 0)), 2),
                    'final_price_with_vat' => round(max(0.0, (float) ($inventory->final_price_with_vat ?? 0)), 2),
                    'quantity' => round(max(0.0, (float) ($inventory->quantity ?? 0)), 3),
                ];
            })
            ->values()
            ->all();
    }
}
