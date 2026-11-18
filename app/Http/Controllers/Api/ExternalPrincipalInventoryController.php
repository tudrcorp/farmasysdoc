<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexExternalPrincipalInventoryRequest;
use App\Models\Inventory;
use App\Models\Product;
use App\Support\Inventory\LasDeliciasBranch;
use Illuminate\Http\JsonResponse;

class ExternalPrincipalInventoryController extends Controller
{
    public function __invoke(IndexExternalPrincipalInventoryRequest $request): JsonResponse
    {
        $branch = LasDeliciasBranch::resolve();

        if ($branch === null) {
            return response()->json([
                'message' => 'No hay una sucursal activa de Las Delicias.',
            ], 404);
        }

        $rows = Inventory::query()
            ->where('branch_id', $branch->id)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->with(['product.productCategory'])
            ->orderBy('product_id')
            ->get();

        $data = $rows->map(function (Inventory $inventory): array {
            /** @var Product $product */
            $product = $inventory->product;

            return [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'brand' => $product->brand,
                'description' => $product->description,
                'product_category_id' => $product->product_category_id,
                'product_category_name' => $product->productCategory?->name,
                'active_ingredient' => $product->active_ingredient,
                'concentration' => $product->concentration,
                'presentation' => $product->presentation,
                'presentation_type' => $product->presentation_type,
                'unit_of_measure' => $product->unit_of_measure,
                'requires_prescription' => (bool) $product->requires_prescription,
                'is_controlled_substance' => (bool) $product->is_controlled_substance,
            ];
        })->values();

        return response()->json([
            'logo_url' => asset('images/logos/farmadoc-ligth.png'),
            'branch' => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'city' => $branch->city,
                'state' => $branch->state,
                'country' => $branch->country,
            ],
            'data' => $data,
        ]);
    }
}
