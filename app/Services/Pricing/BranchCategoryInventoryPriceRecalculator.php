<?php

namespace App\Services\Pricing;

use App\Models\Inventory;
use App\Models\Product;

class BranchCategoryInventoryPriceRecalculator
{
    public function recalculateForBranchAndCategory(int $branchId, int $productCategoryId): void
    {
        if ($branchId <= 0 || $productCategoryId <= 0) {
            return;
        }

        Inventory::query()
            ->where('branch_id', $branchId)
            ->where('product_category_id', $productCategoryId)
            ->orderBy('id')
            ->each(function (Inventory $inventory): void {
                $this->recalculateInventoryRow($inventory);
            });

        $this->syncExpressPricesForCategory($productCategoryId);
    }

    public function recalculateForBranch(int $branchId): void
    {
        if ($branchId <= 0) {
            return;
        }

        Inventory::query()
            ->where('branch_id', $branchId)
            ->orderBy('id')
            ->each(function (Inventory $inventory): void {
                $this->recalculateInventoryRow($inventory);
            });

        $this->syncAllExpressPrices();
    }

    public function recalculateForProduct(Product $product): void
    {
        if (! $product->exists) {
            return;
        }

        Inventory::query()
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->each(function (Inventory $inventory): void {
                $this->recalculateInventoryRow($inventory);
            });

        app(FarmaExpressBranchPriceSynchronizer::class)->syncProduct($product);
    }

    private function recalculateInventoryRow(Inventory $inventory): void
    {
        $inventory->syncFinancialSnapshotFromRelatedProductAndCost();

        if ($inventory->isDirty()) {
            $inventory->saveQuietly();
        }
    }

    private function syncExpressPricesForCategory(int $productCategoryId): void
    {
        Product::query()
            ->select(['id'])
            ->where('product_category_id', $productCategoryId)
            ->orderBy('id')
            ->chunkById(200, function ($products): void {
                $synchronizer = app(FarmaExpressBranchPriceSynchronizer::class);

                foreach ($products as $product) {
                    if ($product instanceof Product) {
                        $synchronizer->syncProduct($product);
                    }
                }
            });
    }

    private function syncAllExpressPrices(): void
    {
        app(FarmaExpressBranchPriceSynchronizer::class)->syncAllProducts();
    }
}
