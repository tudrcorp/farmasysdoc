<?php

namespace App\Services\Dashboard;

use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\SaleItem;
use App\Support\Filament\BranchAuthScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ranking de productos por número de ventas completadas en las que aparece el producto
 * (una venta cuenta una sola vez aunque el ítem repita líneas), respetando {@see BranchAuthScope}.
 */
final class ProductBranchSalesRankingService
{
    /**
     * @return array{labels: list<string>, data: list<int>}
     */
    public function topSelling(int $limit = 20): array
    {
        return $this->ranked($limit, descending: true);
    }

    /**
     * @return array{labels: list<string>, data: list<int>}
     */
    public function leastSelling(int $limit = 20): array
    {
        return $this->ranked($limit, descending: false);
    }

    /**
     * @return array{labels: list<string>, data: list<int>}
     */
    private function ranked(int $limit, bool $descending): array
    {
        $base = SaleItem::query()
            ->whereHas('sale', function (Builder $sale): void {
                $sale->where('status', SaleStatus::Completed);
                BranchAuthScope::applyToSalesQuery($sale);
            });

        $countExpr = 'COUNT(DISTINCT sale_items.sale_id)';

        $ordered = (clone $base)
            ->whereNotNull('product_id')
            ->selectRaw("sale_items.product_id, {$countExpr} as sales_count")
            ->groupBy('sale_items.product_id');

        if ($descending) {
            $ordered->orderByDesc(DB::raw($countExpr));
        } else {
            $ordered->orderBy(DB::raw($countExpr));
        }

        $rows = $ordered->limit($limit)->get();

        if ($rows->isEmpty()) {
            return ['labels' => [], 'data' => []];
        }

        $ids = $rows->pluck('product_id')->filter()->unique()->values()->all();
        $names = Product::query()->whereIn('id', $ids)->pluck('name', 'id')->all();

        $labels = [];
        $data = [];

        foreach ($rows as $row) {
            $pid = (int) $row->product_id;
            $name = $names[$pid] ?? ('Producto #'.$pid);
            $labels[] = Str::limit((string) $name, 34, '…');
            $data[] = (int) $row->sales_count;
        }

        return ['labels' => $labels, 'data' => $data];
    }
}
