<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexExternalInventoryRequest;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ExternalInventoryController extends Controller
{
    public function index(IndexExternalInventoryRequest $request): JsonResponse
    {
        /** @var string $term */
        $term = $request->validated('active_ingredient');

        $products = Product::query()
            ->where('is_active', true)
            ->where('product_type', ProductType::Medication)
            ->whereNotNull('active_ingredient')
            ->where('active_ingredient', '!=', '')
            ->whereActiveIngredientContains($term)
            ->with('inventories')
            ->orderBy('name')
            ->get();

        $data = $products->map(function (Product $product): array {
            $totalAvailable = (float) $product->inventories->sum(
                fn (Inventory $inventory): float => (float) $inventory->available_quantity
            );

            $referenceInventory = $product->inventories->sortBy('sale_price')->first();

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'barcode' => $product->barcode,
                'active_ingredient' => $product->active_ingredient,
                'concentration' => $product->concentration,
                'presentation' => $product->presentation,
                'presentation_type' => $product->presentation_type,
                'sale_price' => $referenceInventory !== null ? $referenceInventory->effectiveSaleUnitPrice() : 0.0,
                'tax_rate' => $referenceInventory !== null ? (float) $referenceInventory->tax_rate : 0.0,
                'requires_prescription' => $product->requires_prescription,
                'is_controlled_substance' => $product->is_controlled_substance,
                'health_registration_number' => $product->health_registration_number,
                'total_available_quantity' => $totalAvailable,
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }
}
