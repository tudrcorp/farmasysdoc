<?php

namespace App\Services\Pricing;

use App\Models\Branch;
use App\Models\BranchCategoryProfitMargin;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;

class BranchCategoryProfitMarginProvisioner
{
    public function provisionForBranch(Branch $branch, ?string $actor = null): void
    {
        $actorLabel = $actor ?? 'sistema';

        ProductCategory::query()
            ->orderBy('id')
            ->pluck('profit_percentage', 'id')
            ->each(function (mixed $profitPercentage, int|string $categoryId) use ($branch, $actorLabel): void {
                BranchCategoryProfitMargin::query()->firstOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'product_category_id' => (int) $categoryId,
                    ],
                    [
                        'profit_percentage' => max(0.0, (float) $profitPercentage),
                        'created_by' => $actorLabel,
                        'updated_by' => $actorLabel,
                    ],
                );
            });
    }

    public function provisionForCategory(ProductCategory $category, ?string $actor = null): void
    {
        $actorLabel = $actor ?? 'sistema';
        $profitPercentage = max(0.0, (float) ($category->profit_percentage ?? 0));

        Branch::query()
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $branchId) use ($category, $profitPercentage, $actorLabel): void {
                BranchCategoryProfitMargin::query()->firstOrCreate(
                    [
                        'branch_id' => $branchId,
                        'product_category_id' => $category->id,
                    ],
                    [
                        'profit_percentage' => $profitPercentage,
                        'created_by' => $actorLabel,
                        'updated_by' => $actorLabel,
                    ],
                );
            });
    }

    /**
     * Copia los márgenes configurados de una sucursal origen hacia la sucursal destino.
     */
    public function copyMarginsFromBranch(Branch $target, Branch $source, ?string $actor = null): void
    {
        if ((int) $target->id === (int) $source->id) {
            return;
        }

        $this->provisionForBranch($source, $actor);
        $this->provisionForBranch($target, $actor);

        $rows = BranchCategoryProfitMargin::query()
            ->where('branch_id', $source->id)
            ->orderBy('product_category_id')
            ->get(['product_category_id', 'profit_percentage'])
            ->map(static fn (BranchCategoryProfitMargin $margin): array => [
                'product_category_id' => (int) $margin->product_category_id,
                'profit_percentage' => max(0.0, (float) $margin->profit_percentage),
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return;
        }

        $this->syncMarginsForBranch($target, $rows, $actor);
    }

    /**
     * @param  list<array{product_category_id: int, profit_percentage: float|int|string}>  $rows
     */
    public function syncMarginsForBranch(Branch $branch, array $rows, ?string $actor = null): void
    {
        $actorLabel = $actor ?? 'sistema';

        DB::transaction(function () use ($branch, $rows, $actorLabel): void {
            foreach ($rows as $row) {
                $categoryId = (int) ($row['product_category_id'] ?? 0);
                if ($categoryId <= 0) {
                    continue;
                }

                $profitPercentage = max(0.0, (float) ($row['profit_percentage'] ?? 0));

                $margin = BranchCategoryProfitMargin::query()->firstOrNew([
                    'branch_id' => $branch->id,
                    'product_category_id' => $categoryId,
                ]);

                if (! $margin->exists) {
                    $margin->created_by = $actorLabel;
                }

                $margin->profit_percentage = $profitPercentage;
                $margin->updated_by = $actorLabel;
                $margin->saveQuietly();
            }

            app(BranchCategoryInventoryPriceRecalculator::class)->recalculateForBranch((int) $branch->id);
            app(BranchCategoryProfitResolver::class)->forgetCache();
        });
    }
}
