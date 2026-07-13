<?php

namespace App\Support\Sales;

use App\Models\FarmaExpressCostStructure;
use App\Models\Inventory;
use App\Models\Product;
use App\Support\Finance\DefaultVatRate;
use Illuminate\Support\Str;

final class ProductUnitPricingForBranch
{
    /**
     * @var array<int, float|null>
     */
    private static array $expressProfitByBranch = [];

    /**
     * Precio unitario de venta para caja y buscador global (precio directo → inventario → lista / express).
     *
     * @return array{unit_net: float, unit_final: float, applies_vat: bool}
     */
    public static function resolve(Product $product, int $branchId, ?Inventory $inventory = null): array
    {
        $directPricing = self::directPricePricing($product);
        if ($directPricing !== null) {
            return $directPricing;
        }

        $appliesVat = (bool) ($product->applies_vat ?? false);
        $vatRate = max(0.0, DefaultVatRate::percent());

        if ($branchId > 0 && ! self::isImportedCategoryProduct($product)) {
            if ($inventory instanceof Inventory) {
                $withoutVat = round(max(0.0, (float) ($inventory->final_price_without_vat ?? 0)), 2);
                $withVat = round(max(0.0, (float) ($inventory->final_price_with_vat ?? 0)), 2);

                if ($withoutVat > 0.0 || $withVat > 0.0) {
                    if ($appliesVat && $withVat > 0.0) {
                        return [
                            'unit_net' => $withoutVat > 0.0 ? $withoutVat : $withVat,
                            'unit_final' => $withVat,
                            'applies_vat' => true,
                        ];
                    }

                    $unit = $withoutVat > 0.0 ? $withoutVat : $withVat;

                    return [
                        'unit_net' => $unit,
                        'unit_final' => $unit,
                        'applies_vat' => $appliesVat,
                    ];
                }
            }
        }

        $baseUnitNet = round(max(0.0, (float) ($product->sale_price ?? 0)), 2);
        $baseUnitFinal = $appliesVat && $vatRate > 0.0
            ? round($baseUnitNet + round($baseUnitNet * $vatRate / 100, 2), 2)
            : $baseUnitNet;

        if ($branchId <= 0 || self::isImportedCategoryProduct($product)) {
            return [
                'unit_net' => $baseUnitNet,
                'unit_final' => $baseUnitFinal,
                'applies_vat' => $appliesVat,
            ];
        }

        $expressProfit = self::expressProfitForBranch($branchId);
        if ($expressProfit === null) {
            return [
                'unit_net' => $baseUnitNet,
                'unit_final' => $baseUnitFinal,
                'applies_vat' => $appliesVat,
            ];
        }

        $expressData = self::expressPriceDataForBranch($product, $branchId);
        $expressWithoutVat = $expressData['final_price_without_vat'] ?? null;
        $expressWithVat = $expressData['final_price_with_vat'] ?? null;

        if ($expressWithoutVat === null) {
            $costPrice = max(0.0, (float) ($product->cost_price ?? 0));
            $expressWithoutVat = round($costPrice + ($costPrice * $expressProfit / 100), 2);
        }

        if ($appliesVat) {
            if ($expressWithVat === null) {
                $expressWithVat = $vatRate > 0.0
                    ? round($expressWithoutVat + round($expressWithoutVat * $vatRate / 100, 2), 2)
                    : $expressWithoutVat;
            }

            return [
                'unit_net' => round(max(0.0, $expressWithoutVat), 2),
                'unit_final' => round(max(0.0, $expressWithVat), 2),
                'applies_vat' => true,
            ];
        }

        $withoutVatForNoVatProduct = $expressWithoutVat > 0.0
            ? $expressWithoutVat
            : ($expressWithVat ?? 0.0);

        return [
            'unit_net' => round(max(0.0, $withoutVatForNoVatProduct), 2),
            'unit_final' => round(max(0.0, $withoutVatForNoVatProduct), 2),
            'applies_vat' => false,
        ];
    }

    /**
     * @param  array<int, float|null>  $expressProfitByBranch
     */
    public static function seedExpressProfitByBranch(array $expressProfitByBranch): void
    {
        self::$expressProfitByBranch = $expressProfitByBranch;
    }

    public static function forgetExpressProfitCache(): void
    {
        self::$expressProfitByBranch = [];
    }

    /**
     * @return array{unit_net: float, unit_final: float, applies_vat: bool}|null
     */
    private static function directPricePricing(Product $product): ?array
    {
        $rawDirectPrice = $product->getAttribute('direct_price');
        if ($rawDirectPrice === null || $rawDirectPrice === '') {
            return null;
        }

        $unitNet = round(max(0.0, (float) $rawDirectPrice), 2);
        $appliesVat = (bool) ($product->applies_vat ?? false);
        $vatRate = max(0.0, DefaultVatRate::percent());

        if ($appliesVat && $vatRate > 0.0) {
            return [
                'unit_net' => $unitNet,
                'unit_final' => round($unitNet + round($unitNet * $vatRate / 100, 2), 2),
                'applies_vat' => true,
            ];
        }

        return [
            'unit_net' => $unitNet,
            'unit_final' => $unitNet,
            'applies_vat' => $appliesVat,
        ];
    }

    private static function isImportedCategoryProduct(Product $product): bool
    {
        $product->loadMissing('productCategory');
        $name = $product->productCategory?->name;
        if (! is_string($name) || trim($name) === '') {
            return false;
        }

        return mb_strtoupper(Str::ascii(trim($name))) === 'IMPORTADOS';
    }

    /**
     * @return array{final_price_without_vat: float, final_price_with_vat: ?float}|null
     */
    private static function expressPriceDataForBranch(Product $product, int $branchId): ?array
    {
        $raw = $product->express_branch_prices;
        if (! is_array($raw)) {
            return null;
        }

        $entry = $raw[(string) $branchId] ?? $raw[$branchId] ?? null;
        if (! is_array($entry)) {
            return null;
        }

        $withoutVatRaw = $entry['final_price_without_vat'] ?? null;
        $withVatRaw = $entry['final_price_with_vat'] ?? null;
        $withoutVat = is_numeric($withoutVatRaw) ? max(0.0, (float) $withoutVatRaw) : null;
        $withVat = is_numeric($withVatRaw) ? max(0.0, (float) $withVatRaw) : null;

        if ($withoutVat === null && $withVat === null) {
            return null;
        }

        if ($withoutVat === null && $withVat !== null) {
            $withoutVat = $withVat;
        }

        return [
            'final_price_without_vat' => round(max(0.0, (float) $withoutVat), 2),
            'final_price_with_vat' => $withVat !== null ? round($withVat, 2) : null,
        ];
    }

    private static function expressProfitForBranch(int $branchId): ?float
    {
        if ($branchId <= 0) {
            return null;
        }

        if (array_key_exists($branchId, self::$expressProfitByBranch)) {
            return self::$expressProfitByBranch[$branchId];
        }

        $profit = FarmaExpressCostStructure::query()
            ->where('branch_id', $branchId)
            ->value('profit_percentage');

        $resolved = is_numeric($profit)
            ? max(0.0, (float) $profit)
            : null;

        self::$expressProfitByBranch[$branchId] = $resolved;

        return $resolved;
    }
}
