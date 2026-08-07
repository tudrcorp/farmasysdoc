<?php

namespace App\Filament\Resources\InventoryAudits\Support;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Inventory\InventoryFinancialPricingBreakdown;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

final class InventoryAuditApplyUpdateForm
{
    /**
     * Campos del formulario «Aplicar actualización» (cantidad, categoría, costo y desglose).
     *
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('counted_quantity')
                ->label('Cantidad contada')
                ->numeric()
                ->required()
                ->minValue(0)
                ->step(0.001),
            Select::make('product_category_id')
                ->label('Categoría')
                ->options(fn (): array => ProductCategory::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->native(false)
                ->required()
                ->live()
                ->helperText('Al cambiar la categoría se recalculan los precios con el margen de esa categoría en esta sucursal.')
                ->prefixIcon(Heroicon::Tag),
            TextInput::make('new_cost_price')
                ->label('Nuevo costo (opcional)')
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->live(debounce: 400)
                ->helperText('Déjelo vacío para no modificar el costo. Puede bajar el costo si corresponde.')
                ->prefixIcon(Heroicon::CurrencyDollar),
            Hidden::make('_branch_id')->dehydrated(false),
            Hidden::make('_current_cost_price')->dehydrated(false),
            Hidden::make('_applies_vat')->dehydrated(false),
            Hidden::make('_original_product_category_id')->dehydrated(false),
            Placeholder::make('pricing_breakdown')
                ->label('Detalle del cálculo')
                ->content(function (Get $get): HtmlString {
                    $state = [
                        'new_cost_price' => $get('new_cost_price'),
                        'product_category_id' => $get('product_category_id'),
                        '_branch_id' => $get('_branch_id'),
                        '_current_cost_price' => $get('_current_cost_price'),
                        '_applies_vat' => $get('_applies_vat'),
                        '_original_product_category_id' => $get('_original_product_category_id'),
                    ];

                    $breakdown = InventoryFinancialPricingBreakdown::fromFormState($state);
                    if ($breakdown === null) {
                        return new HtmlString('');
                    }

                    return InventoryFinancialPricingBreakdown::toHtml($breakdown);
                })
                ->visible(function (Get $get): bool {
                    return InventoryFinancialPricingBreakdown::shouldDisplay([
                        'new_cost_price' => $get('new_cost_price'),
                        'product_category_id' => $get('product_category_id'),
                        '_original_product_category_id' => $get('_original_product_category_id'),
                    ]);
                }),
        ];
    }

    /**
     * @return array{
     *     counted_quantity: float,
     *     new_cost_price: null,
     *     product_category_id: int|null,
     *     _branch_id: int,
     *     _current_cost_price: float,
     *     _applies_vat: bool,
     *     _original_product_category_id: int|null
     * }
     */
    public static function fillFromProductAndBranch(
        Product $product,
        int $branchId,
        float $systemQuantity,
        float $systemCostPrice,
    ): array {
        $categoryId = $product->product_category_id !== null
            ? (int) $product->product_category_id
            : null;

        return [
            'counted_quantity' => $systemQuantity,
            'new_cost_price' => null,
            'product_category_id' => $categoryId,
            '_branch_id' => $branchId,
            '_current_cost_price' => round($systemCostPrice, 2),
            '_applies_vat' => (bool) ($product->applies_vat ?? false),
            '_original_product_category_id' => $categoryId,
        ];
    }
}
