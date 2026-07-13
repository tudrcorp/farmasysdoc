<?php

namespace App\Services\Pricing;

use App\Models\BranchCategoryProfitMargin;
use App\Models\ProductCategory;

class BranchCategoryProfitResolver
{
    /**
     * @var array<string, float>
     */
    private array $cache = [];

    public function resolve(?int $branchId, ?int $productCategoryId): float
    {
        if ($productCategoryId === null || $productCategoryId <= 0) {
            return 0.0;
        }

        if ($branchId !== null && $branchId > 0) {
            $cacheKey = $branchId.':'.$productCategoryId;

            if (array_key_exists($cacheKey, $this->cache)) {
                return $this->cache[$cacheKey];
            }

            $margin = BranchCategoryProfitMargin::query()
                ->where('branch_id', $branchId)
                ->where('product_category_id', $productCategoryId)
                ->value('profit_percentage');

            if ($margin !== null) {
                $resolved = max(0.0, (float) $margin);
                $this->cache[$cacheKey] = $resolved;

                return $resolved;
            }
        }

        $category = ProductCategory::query()
            ->whereKey($productCategoryId)
            ->where('is_active', true)
            ->first();

        if ($category === null) {
            return 0.0;
        }

        return max(0.0, (float) $category->profit_percentage);
    }

    public function forgetCache(): void
    {
        $this->cache = [];
    }
}
