<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicProductSearchRequest;
use App\Support\Shop\ShopCatalog;
use Illuminate\Http\JsonResponse;

class PublicProductSearchController extends Controller
{
    public function __invoke(PublicProductSearchRequest $request): JsonResponse
    {
        $term = $request->term();
        $categoryId = $request->categoryId();
        $onlyOffers = $request->onlyOffers();

        if ($categoryId === null && ! $onlyOffers && mb_strlen($term) < 2) {
            return response()->json([
                'data' => [],
            ]);
        }

        $items = ShopCatalog::search(
            $term,
            $categoryId,
            'relevance',
            $onlyOffers,
            24,
        );

        return response()->json([
            'data' => $items,
        ])->header('Cache-Control', 'public, max-age=15, stale-while-revalidate=45');
    }
}
