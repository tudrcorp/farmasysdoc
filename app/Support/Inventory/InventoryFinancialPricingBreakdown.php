<?php

namespace App\Support\Inventory;

use App\Models\BranchCategoryProfitMargin;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Pricing\BranchCategoryProfitResolver;
use App\Support\Finance\DefaultVatRate;
use Illuminate\Support\HtmlString;

final class InventoryFinancialPricingBreakdown
{
    /**
     * @return array{
     *     category_id: int|null,
     *     category_name: string,
     *     profit_percentage: float,
     *     profit_source_label: string,
     *     applies_vat: bool,
     *     vat_rate_percent: float,
     *     cost_price: float,
     *     vat_cost_amount: float,
     *     cost_plus_vat: float,
     *     final_price_without_vat: float,
     *     vat_final_price_amount: float,
     *     final_price_with_vat: float
     * }|null
     */
    public static function compute(
        float $cost,
        int $branchId,
        ?int $productCategoryId,
        bool $appliesVat,
    ): ?array {
        if ($branchId <= 0) {
            return null;
        }

        $categoryId = ($productCategoryId !== null && $productCategoryId > 0)
            ? $productCategoryId
            : null;

        $category = $categoryId !== null
            ? ProductCategory::query()->whereKey($categoryId)->first(['id', 'name', 'profit_percentage'])
            : null;

        $product = new Product([
            'product_category_id' => $categoryId,
            'applies_vat' => $appliesVat,
        ]);

        $snapshot = Inventory::financialSnapshotFromCostAndProduct($cost, $product, $branchId);

        $profitPercentage = $categoryId !== null
            ? app(BranchCategoryProfitResolver::class)->resolve($branchId, $categoryId)
            : 0.0;

        $hasBranchMargin = $categoryId !== null && BranchCategoryProfitMargin::query()
            ->where('branch_id', $branchId)
            ->where('product_category_id', $categoryId)
            ->exists();

        $vatRate = $appliesVat ? max(0.0, DefaultVatRate::percent()) : 0.0;

        return [
            'category_id' => $categoryId,
            'category_name' => (string) ($category?->name ?? 'Sin categoría'),
            'profit_percentage' => round($profitPercentage, 4),
            'profit_source_label' => $categoryId === null
                ? 'Sin margen (sin categoría)'
                : ($hasBranchMargin
                    ? 'Margen de esta sucursal para la categoría'
                    : 'Margen por defecto de la categoría'),
            'applies_vat' => $appliesVat,
            'vat_rate_percent' => round($vatRate, 2),
            'cost_price' => round((float) $snapshot['cost_price'], 2),
            'vat_cost_amount' => round((float) $snapshot['vat_cost_amount'], 2),
            'cost_plus_vat' => round((float) $snapshot['cost_plus_vat'], 2),
            'final_price_without_vat' => round((float) $snapshot['final_price_without_vat'], 2),
            'vat_final_price_amount' => round((float) $snapshot['vat_final_price_amount'], 2),
            'final_price_with_vat' => round((float) $snapshot['final_price_with_vat'], 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function fromFormState(array $state): ?array
    {
        $branchId = (int) ($state['_branch_id'] ?? 0);
        $categoryId = filled($state['product_category_id'] ?? null)
            ? (int) $state['product_category_id']
            : null;
        $appliesVat = (bool) ($state['_applies_vat'] ?? false);

        $costRaw = $state['new_cost_price'] ?? null;
        $cost = ($costRaw !== null && $costRaw !== '')
            ? (float) $costRaw
            : (float) ($state['_current_cost_price'] ?? 0);

        return self::compute($cost, $branchId, $categoryId, $appliesVat);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function shouldDisplay(array $state): bool
    {
        if (filled($state['new_cost_price'] ?? null)) {
            return true;
        }

        $selected = $state['product_category_id'] ?? null;
        if (! filled($selected)) {
            return false;
        }

        $original = $state['_original_product_category_id'] ?? null;

        return (int) $selected !== (int) ($original ?? 0);
    }

    /**
     * @param  array{
     *     category_id: int|null,
     *     category_name: string,
     *     profit_percentage: float,
     *     profit_source_label: string,
     *     applies_vat: bool,
     *     vat_rate_percent: float,
     *     cost_price: float,
     *     vat_cost_amount: float,
     *     cost_plus_vat: float,
     *     final_price_without_vat: float,
     *     vat_final_price_amount: float,
     *     final_price_with_vat: float
     * }  $breakdown
     */
    public static function toHtml(array $breakdown): HtmlString
    {
        $money = static fn (float $value): string => '$ '.number_format($value, 2, ',', '.');
        $percent = static fn (float $value): string => number_format($value, 2, ',', '.').' %';

        $rows = [
            ['Categoría', e($breakdown['category_name'])],
            ['Margen de ganancia', e($percent($breakdown['profit_percentage'])).' <span class="text-gray-500 dark:text-gray-400">('.e($breakdown['profit_source_label']).')</span>'],
            ['Costo base', e($money($breakdown['cost_price']))],
            [
                'IVA sobre costo'.($breakdown['applies_vat'] ? ' ('.e($percent($breakdown['vat_rate_percent'])).')' : ' (no grava)'),
                e($money($breakdown['vat_cost_amount'])),
            ],
            ['Costo + IVA', e($money($breakdown['cost_plus_vat']))],
            [
                'Precio final sin IVA',
                e($money($breakdown['final_price_without_vat']))
                    .' <span class="text-gray-500 dark:text-gray-400">(costo + margen)</span>',
            ],
            [
                'IVA sobre precio final'.($breakdown['applies_vat'] ? ' ('.e($percent($breakdown['vat_rate_percent'])).')' : ' (no grava)'),
                e($money($breakdown['vat_final_price_amount'])),
            ],
            ['Precio final con IVA', '<strong>'.e($money($breakdown['final_price_with_vat'])).'</strong>'],
        ];

        $html = '<div class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm dark:border-white/10 dark:bg-white/5">';
        $html .= '<p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Cálculo aplicado en esta sucursal</p>';
        $html .= '<dl class="space-y-1.5">';

        foreach ($rows as [$label, $value]) {
            $html .= '<div class="flex items-start justify-between gap-3">';
            $html .= '<dt class="text-gray-600 dark:text-gray-300">'.$label.'</dt>';
            $html .= '<dd class="text-right tabular-nums text-gray-950 dark:text-white">'.$value.'</dd>';
            $html .= '</div>';
        }

        $html .= '</dl>';
        $html .= '<p class="mt-3 text-xs text-gray-500 dark:text-gray-400">';
        $html .= 'Fórmulas: costo + IVA = costo × (1 + IVA%); precio sin IVA = costo × (1 + margen%); precio con IVA = precio sin IVA × (1 + IVA%).';
        $html .= '</p></div>';

        return new HtmlString($html);
    }
}
