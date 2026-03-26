<?php

namespace App\Services\Marketing;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Client;
use App\Models\MarketingBroadcast;
use App\Models\MarketingCampaign;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MarketingAnalyticsService
{
    /**
     * Ventas consideradas para analítica de marketing.
     *
     * @return Builder<Sale>
     */
    public function completedSalesQuery(): Builder
    {
        return Sale::query()->where('status', SaleStatus::Completed);
    }

    /**
     * @return array{
     *     total_clients: int,
     *     clients_with_email: int,
     *     clients_with_phone: int,
     *     active_campaigns: int,
     *     broadcasts_completed_30d: int,
     *     total_completed_sales: int,
     *     revenue_total: string
     * }
     */
    public function dashboardSummary(): array
    {
        $totalClients = Client::query()->count();
        $clientsWithEmail = Client::query()->whereNotNull('email')->where('email', '!=', '')->count();
        $clientsWithPhone = Client::query()->whereNotNull('phone')->where('phone', '!=', '')->count();
        $activeCampaigns = MarketingCampaign::query()->where('status', 'active')->count();
        $broadcastsDone = MarketingBroadcast::query()
            ->where('status', 'completed')
            ->where('completed_at', '>=', Carbon::now()->subDays(30))
            ->count();

        $sales = $this->completedSalesQuery();
        $totalSales = (clone $sales)->count();
        $revenue = (clone $sales)->sum('total');

        return [
            'total_clients' => $totalClients,
            'clients_with_email' => $clientsWithEmail,
            'clients_with_phone' => $clientsWithPhone,
            'active_campaigns' => $activeCampaigns,
            'broadcasts_completed_30d' => $broadcastsDone,
            'total_completed_sales' => $totalSales,
            'revenue_total' => number_format((float) $revenue, 2, ',', '.'),
        ];
    }

    /**
     * Ingresos por sucursal (para gráfico de barras).
     *
     * @return array{labels: list<string>, data: list<float>}
     */
    public function branchRevenueChart(int $limit = 12): array
    {
        $rows = $this->completedSalesQuery()
            ->selectRaw('branch_id, SUM(CAST(total AS DECIMAL(14,2))) as revenue')
            ->groupBy('branch_id')
            ->orderByDesc(DB::raw('revenue'))
            ->limit($limit)
            ->get();

        $branchIds = $rows->pluck('branch_id')->filter()->unique()->values();
        $names = Branch::query()->whereIn('id', $branchIds)->pluck('name', 'id');

        $labels = [];
        $data = [];
        foreach ($rows as $row) {
            $labels[] = $names[$row->branch_id] ?? ('Sucursal #'.$row->branch_id);
            $data[] = (float) $row->revenue;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Productos más vendidos (por líneas completadas), usando snapshot de nombre.
     *
     * @return array{labels: list<string>, data: list<float>}
     */
    public function topProductsChart(int $limit = 10): array
    {
        $q = SaleItem::query()
            ->whereHas('sale', fn (Builder $s) => $s->where('status', SaleStatus::Completed));

        $rows = $q
            ->selectRaw('COALESCE(NULLIF(TRIM(product_name_snapshot), ""), CONCAT("Producto #", product_id)) as pname, SUM(quantity) as qty')
            ->groupBy('pname')
            ->orderByDesc(DB::raw('qty'))
            ->limit($limit)
            ->get();

        return [
            'labels' => $rows->pluck('pname')->map(fn ($n) => (string) $n)->all(),
            'data' => $rows->pluck('qty')->map(fn ($v) => (float) $v)->all(),
        ];
    }

    /**
     * Ranking de sucursales por número de ventas completadas.
     *
     * @return Collection<int, object{branch_id: int, branch_name: string, sales_count: int, revenue: float}>
     */
    public function branchRankingBySales(int $limit = 15): Collection
    {
        $rows = $this->completedSalesQuery()
            ->selectRaw('branch_id, COUNT(*) as sales_count, SUM(CAST(total AS DECIMAL(14,2))) as revenue')
            ->groupBy('branch_id')
            ->orderByDesc('sales_count')
            ->limit($limit)
            ->get();

        $ids = $rows->pluck('branch_id')->filter()->unique()->values();
        $names = Branch::query()->whereIn('id', $ids)->pluck('name', 'id');

        return $rows->map(function ($row) use ($names) {
            return (object) [
                'branch_id' => (int) $row->branch_id,
                'branch_name' => (string) ($names[$row->branch_id] ?? 'Sucursal #'.$row->branch_id),
                'sales_count' => (int) $row->sales_count,
                'revenue' => (float) $row->revenue,
            ];
        });
    }

    /**
     * Ranking de sucursales con producto más vendido por sucursal (ventas completadas).
     *
     * @return Collection<int, object{branch_id: int, branch_name: string, sales_count: int, revenue: float, revenue_formatted: string, top_product: string}>
     */
    public function branchRankingWithTopProducts(int $limit = 20): Collection
    {
        return $this->branchRankingBySales($limit)->map(function (object $row): object {
            $top = $this->topProductNameForBranch($row->branch_id);

            return (object) [
                'branch_id' => $row->branch_id,
                'branch_name' => $row->branch_name,
                'sales_count' => $row->sales_count,
                'revenue' => $row->revenue,
                'revenue_formatted' => number_format($row->revenue, 2, ',', '.'),
                'top_product' => $top ?? '—',
            ];
        });
    }

    /**
     * Producto con mayor cantidad vendida en una sucursal (ventas completadas).
     */
    public function topProductNameForBranch(int $branchId): ?string
    {
        $row = SaleItem::query()
            ->whereHas('sale', fn (Builder $s) => $s
                ->where('status', SaleStatus::Completed)
                ->where('branch_id', $branchId))
            ->selectRaw('COALESCE(NULLIF(TRIM(product_name_snapshot), ""), CONCAT("Producto #", product_id)) as pname, SUM(quantity) as qty')
            ->groupBy('pname')
            ->orderByDesc(DB::raw('qty'))
            ->first();

        return $row ? (string) $row->pname : null;
    }

    /**
     * Top clientes por sucursal según frecuencia de compra (proxy de “visitas”).
     *
     * @return Collection<int, object{client_id: int, client_name: string, purchase_count: int}>
     */
    public function topClientsByBranchPurchases(int $branchId, int $limit = 10): Collection
    {
        $rows = $this->completedSalesQuery()
            ->where('branch_id', $branchId)
            ->whereNotNull('client_id')
            ->selectRaw('client_id, COUNT(*) as purchase_count')
            ->groupBy('client_id')
            ->orderByDesc('purchase_count')
            ->limit($limit)
            ->get();

        $clientIds = $rows->pluck('client_id')->filter()->unique()->values();
        $clientNames = Client::query()->whereIn('id', $clientIds)->pluck('name', 'id');

        return $rows->map(function ($row) use ($clientNames) {
            return (object) [
                'client_id' => (int) $row->client_id,
                'client_name' => (string) ($clientNames[$row->client_id] ?? 'Cliente #'.$row->client_id),
                'purchase_count' => (int) $row->purchase_count,
            ];
        });
    }

    /**
     * Métricas de comportamiento para la ficha de cliente (marketing).
     *
     * @return array{
     *     purchases_count: int,
     *     total_spent: string,
     *     avg_ticket: string,
     *     max_purchase: string,
     *     last_purchase_at: ?string,
     *     favorite_product: string,
     *     branches_visited: int,
     *     first_purchase_at: ?string
     * }
     */
    public function clientBehaviorMetrics(Client $client): array
    {
        $sales = $this->completedSalesQuery()->where('client_id', $client->id);

        $purchasesCount = (clone $sales)->count();
        $totalSpent = (float) (clone $sales)->sum('total');
        $maxPurchase = (float) (clone $sales)->max('total');
        $avgTicket = $purchasesCount > 0 ? $totalSpent / $purchasesCount : 0.0;

        $last = (clone $sales)->orderByDesc('sold_at')->orderByDesc('id')->first();
        $first = (clone $sales)->orderBy('sold_at')->orderBy('id')->first();

        $branchesVisited = (clone $sales)->whereNotNull('branch_id')->distinct()->count('branch_id');

        $favRow = SaleItem::query()
            ->whereHas('sale', fn (Builder $s) => $s
                ->where('status', SaleStatus::Completed)
                ->where('client_id', $client->id))
            ->selectRaw('COALESCE(NULLIF(TRIM(product_name_snapshot), ""), CONCAT("Producto #", product_id)) as pname, SUM(quantity) as qty')
            ->groupBy('pname')
            ->orderByDesc(DB::raw('qty'))
            ->first();

        return [
            'purchases_count' => $purchasesCount,
            'total_spent' => number_format($totalSpent, 2, ',', '.'),
            'avg_ticket' => number_format($avgTicket, 2, ',', '.'),
            'max_purchase' => number_format($maxPurchase, 2, ',', '.'),
            'last_purchase_at' => $last?->sold_at?->format('d/m/Y H:i'),
            'first_purchase_at' => $first?->sold_at?->format('d/m/Y H:i'),
            'favorite_product' => $favRow ? (string) $favRow->pname : '—',
            'branches_visited' => (int) $branchesVisited,
        ];
    }
}
