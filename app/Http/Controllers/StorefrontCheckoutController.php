<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorefrontCheckoutRequest;
use App\Support\Shop\ShopCart;
use Illuminate\Http\JsonResponse;

class StorefrontCheckoutController extends Controller
{
    public function __invoke(StorefrontCheckoutRequest $request, ShopCart $cart): JsonResponse
    {
        $cart->replace($request->quantitiesByProductId());

        if ($cart->isEmpty()) {
            return response()->json([
                'message' => 'Esos productos ya no están disponibles en inventario.',
            ], 422);
        }

        return response()->json([
            'redirect' => route('storefront.pay'),
        ]);
    }
}
