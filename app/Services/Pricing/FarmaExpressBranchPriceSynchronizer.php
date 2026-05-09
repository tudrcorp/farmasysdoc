<?php

namespace App\Services\Pricing;

use App\Models\FarmaExpressCostStructure;
use App\Models\Product;
use App\Support\Finance\DefaultVatRate;
use Illuminate\Support\Collection;

class FarmaExpressBranchPriceSynchronizer
{
    public function syncAllProducts(): void
    {
        $structures = $this->costStructures();
        $vatRate = max(0.0, min(100.0, DefaultVatRate::percent()));

        Product::query()
            ->select(['id', 'cost_price', 'applies_vat'])
            ->orderBy('id', 'asc')
            ->chunkById(200, function (Collection $products) use ($structures, $vatRate): void {
                foreach ($products as $product) {
                    if (! $product instanceof Product) {
                        continue;
                    }

                    Product::query()
                        ->whereKey($product->id)
                        ->update([
                            'express_branch_prices' => $this->buildPricesForProduct(
                                $product,
                                $structures,
                                $vatRate,
                            ),
                        ]);
                }
            });
    }

    public function syncProduct(Product $product): void
    {
        if (! $product->exists) {
            return;
        }

        Product::query()
            ->whereKey($product->id)
            ->update([
                'express_branch_prices' => $this->buildPricesForProduct(
                    $product,
                    $this->costStructures(),
                    max(0.0, min(100.0, DefaultVatRate::percent())),
                ),
            ]);
    }

    /**
     * @return Collection<int, FarmaExpressCostStructure>
     */
    private function costStructures(): Collection
    {
        return FarmaExpressCostStructure::query()
            ->select(['branch_id', 'profit_percentage'])
            ->with(['branch:id,name'])
            ->orderBy('branch_id', 'asc')
            ->get();
    }

    /**
     * @param  Collection<int, FarmaExpressCostStructure>  $structures
     * @return array<string, array<string, float|int|string>>
     */
    private function buildPricesForProduct(Product $product, Collection $structures, float $vatRate): array
    {
        $costPrice = max(0.0, (float) ($product->cost_price ?? 0));
        $appliesVat = (bool) $product->applies_vat;
        $prices = [];

        foreach ($structures as $structure) {
            $profitPercentage = max(0.0, (float) $structure->profit_percentage);
            $finalPriceWithoutVat = round($costPrice + ($costPrice * $profitPercentage / 100), 2);
            $finalPriceWithVat = $appliesVat
                ? round($finalPriceWithoutVat + ($finalPriceWithoutVat * $vatRate / 100), 2)
                : $finalPriceWithoutVat;

            $prices[(string) $structure->branch_id] = [
                'branch_id' => (int) $structure->branch_id,
                'branch_name' => (string) ($structure->branch?->name ?? ('Sucursal #'.$structure->branch_id)),
                'profit_percentage' => $profitPercentage,
                'final_price_without_vat' => $finalPriceWithoutVat,
                'final_price_with_vat' => $finalPriceWithVat,
            ];
        }

        return $prices;
    }
}
