<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PublicProductSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json([
                'data' => [],
            ]);
        }

        $safeTerm = mb_substr($term, 0, 100);
        $like = '%'.addcslashes($safeTerm, '%_\\').'%';
        $prefix = addcslashes($safeTerm, '%_\\').'%';

        $hasSku = Schema::hasColumn('products', 'sku');
        $hasSlug = Schema::hasColumn('products', 'slug');

        $select = [
            'products.id',
            'products.name',
            'products.barcode',
            'products.brand',
            'products.presentation_type',
            'products.active_ingredient',
            'products.sale_price',
            'products.applies_vat',
            DB::raw('COALESCE(SUM(inventories.quantity - inventories.reserved_quantity), 0) as stock_available'),
        ];

        if ($hasSku) {
            $select[] = 'products.sku';
        }

        $query = DB::table('products')
            ->leftJoin('inventories', 'inventories.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->where(function ($where) use ($like, $hasSku, $hasSlug, $safeTerm): void {
                $where->where('products.name', 'like', $like)
                    ->orWhere('products.barcode', 'like', $like)
                    ->orWhere('products.brand', 'like', $like)
                    ->orWhereRaw('LOWER(products.active_ingredient) LIKE ?', ['%'.mb_strtolower($safeTerm).'%']);

                if ($hasSku) {
                    $where->orWhere('products.sku', 'like', $like);
                }

                if ($hasSlug) {
                    $where->orWhere('products.slug', 'like', $like);
                }
            })
            ->select($select)
            ->groupBy([
                'products.id',
                'products.name',
                'products.barcode',
                'products.brand',
                'products.presentation_type',
                'products.active_ingredient',
                'products.sale_price',
                'products.applies_vat',
                ...($hasSku ? ['products.sku'] : []),
            ])
            ->havingRaw('COALESCE(SUM(inventories.quantity - inventories.reserved_quantity), 0) > 1')
            ->orderByRaw(
                'CASE
                    WHEN products.barcode = ? THEN 0
                    WHEN products.name LIKE ? THEN 1
                    WHEN products.barcode LIKE ? THEN 2
                    ELSE 3
                END',
                [$safeTerm, $prefix, $prefix],
            )
            ->orderBy('products.name')
            ->limit(12);

        $rows = $query->get();

        $items = $rows->map(function (object $row): array {
            $ingredient = $this->formatActiveIngredient($row->active_ingredient ?? null);

            return [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'barcode' => filled($row->barcode ?? null) ? (string) $row->barcode : '—',
                'brand' => filled($row->brand ?? null) ? (string) $row->brand : '—',
                'presentation' => filled($row->presentation_type ?? null) ? (string) $row->presentation_type : '—',
                'active_ingredient' => $ingredient,
                'sale_price' => round((float) ($row->sale_price ?? 0), 2),
                'applies_vat' => (bool) ($row->applies_vat ?? false),
                'stock_available' => round((float) ($row->stock_available ?? 0), 3),
            ];
        })->values()->all();

        return response()->json([
            'data' => $items,
        ]);
    }

    private function formatActiveIngredient(mixed $value): string
    {
        if (is_array($value)) {
            $items = array_values(array_filter(array_map(
                static fn (mixed $item): string => is_string($item) ? trim($item) : '',
                $value,
            )));

            return $items !== [] ? implode(', ', $items) : '—';
        }

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return '—';
    }
}
