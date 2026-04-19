<?php

namespace App\Http\Controllers\Api;

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
            ->whereHas('productCategory', function ($query): void {
                $query
                    ->where('is_active', true)
                    ->where('is_medication', true);
            })
            ->whereNotNull('active_ingredient')
            ->where('active_ingredient', '!=', '')
            ->whereActiveIngredientContains($term)
            ->with(['inventories', 'productCategory'])
            ->orderBy('name')
            ->get();

        $data = $products->map(function (Product $product): array {
            $totalAvailable = (float) $product->inventories->sum(
                fn (Inventory $inventory): float => (float) $inventory->available_quantity
            );

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'barcode' => $product->barcode,
                'active_ingredient' => $product->active_ingredient,
                'concentration' => $product->concentration,
                'presentation' => $product->presentation,
                'presentation_type' => $product->presentation_type,
                'sale_price' => $product->effectiveSaleUnitPrice(),
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
