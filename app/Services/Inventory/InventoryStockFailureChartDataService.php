<?php

namespace App\Services\Inventory;

use App\Models\Branch;
use App\Models\InventoryStockFailure;
use Illuminate\Support\Str;

class InventoryStockFailureChartDataService
{
    /**
     * @return array{labels: list<string>, data: list<int>}
     */
    public function failuresByProduct(?int $branchId = null, int $limit = 15): array
    {
        $rows = InventoryStockFailure::query()
            ->when(
                filled($branchId),
                fn ($query) => $query->where('branch_id', $branchId),
            )
            ->selectRaw('product_id, product_name, COUNT(*) as failure_count')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('failure_count')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'labels' => [],
                'data' => [],
            ];
        }

        $labels = [];
        $data = [];

        foreach ($rows as $row) {
            $labels[] = Str::limit((string) $row->product_name, 28, '…');
            $data[] = (int) $row->failure_count;
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    /**
     * Todas las sucursales activas en un solo gráfico, ordenadas de mayor a menor.
     *
     * @return array{labels: list<string>, data: list<int>, branch_ids: list<int>}
     */
    public function failuresByBranch(): array
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($branches->isEmpty()) {
            return [
                'labels' => [],
                'data' => [],
                'branch_ids' => [],
            ];
        }

        $counts = InventoryStockFailure::query()
            ->whereNotNull('branch_id')
            ->selectRaw('branch_id, COUNT(*) as failure_count')
            ->groupBy('branch_id')
            ->pluck('failure_count', 'branch_id');

        $items = [];

        foreach ($branches as $branch) {
            $items[] = [
                'branch_id' => (int) $branch->id,
                'label' => Str::limit((string) $branch->name, 28, '…'),
                'count' => (int) ($counts[$branch->id] ?? 0),
            ];
        }

        usort(
            $items,
            fn (array $left, array $right): int => $right['count'] <=> $left['count']
                ?: strcmp($left['label'], $right['label']),
        );

        return [
            'labels' => array_column($items, 'label'),
            'data' => array_column($items, 'count'),
            'branch_ids' => array_column($items, 'branch_id'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function branchFilterOptions(): array
    {
        $options = ['' => 'Todas las sucursales'];

        Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->each(function (Branch $branch) use (&$options): void {
                $options[(string) $branch->id] = $branch->name;
            });

        return $options;
    }

    public function branchLabelForFilter(?string $filter): ?string
    {
        if (! filled($filter) || ! is_numeric($filter)) {
            return null;
        }

        return Branch::query()
            ->whereKey((int) $filter)
            ->value('name');
    }
}
