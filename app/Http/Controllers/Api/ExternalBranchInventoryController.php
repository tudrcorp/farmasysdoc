<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexExternalBranchInventoryRequest;
use App\Models\Branch;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;

class ExternalBranchInventoryController extends Controller
{
    public function index(IndexExternalBranchInventoryRequest $request): JsonResponse
    {
        /** @var int $branchId */
        $branchId = $request->validated('branch_id');

        $branch = Branch::query()
            ->where('is_active', true)
            ->whereKey($branchId)
            ->firstOrFail(['id', 'code', 'name', 'city', 'state', 'country']);

        $rows = Inventory::query()
            ->where('branch_id', $branchId)
            ->with(['product.productCategory'])
            ->orderBy('product_id')
            ->get();

        $data = $rows->map(function (Inventory $inventory): array {
            $product = $inventory->product;

            return [
                'inventory_id' => $inventory->id,
                'product_id' => $inventory->product_id,
                'sku' => $product?->sku,
                'name' => $product?->name,
                'barcode' => $product?->barcode,
                'product_category_id' => $product?->product_category_id,
                'product_category_name' => $product?->productCategory?->name,
                'active_ingredient' => $product?->active_ingredient,
                'concentration' => $product?->concentration,
                'presentation_type' => $product?->presentation_type,
                'product_is_active' => $product !== null ? (bool) $product->is_active : null,
                'quantity' => (float) $inventory->quantity,
                'reserved_quantity' => (float) $inventory->reserved_quantity,
                'available_quantity' => (float) $inventory->available_quantity,
                'sale_price' => (float) ($product?->sale_price ?? 0),
                'effective_sale_unit_price' => $product !== null ? $product->effectiveSaleUnitPrice() : 0.0,
                'discount_percent' => (float) ($product?->discount_percent ?? 0),
                'allow_negative_stock' => (bool) $inventory->allow_negative_stock,
            ];
        });

        return response()->json([
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
