<?php

namespace App\Services\Sales;

use App\Enums\SaleStatus;
use App\Models\PhysicalCashBox;
use App\Models\Sale;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use App\Support\Sales\PosPaymentMethodOptions;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Consolida ventas del turno de caja física (desde apertura hasta cierre).
 */
final class PhysicalCashBoxShiftReportBuilder
{
    /**
     * @return array{
     *     cashier_name: string,
     *     branch_name: string,
     *     opened_at_label: string,
     *     closed_at_label: string,
     *     summary: array{
     *         sale_count: int,
     *         customers_served: int,
     *         quantity_sold: float,
     *         grand_total: float,
     *         payment_usd_sum: float,
     *         payment_ves_sum: float,
     *     },
     *     payment_breakdown: list<array{
     *         method: string,
     *         label: string,
     *         count: int,
     *         total_document: float,
     *         payment_usd: float,
     *         payment_ves: float,
     *     }>,
     *     payment_breakdown_totals: array{
     *         count: int,
     *         total_document: float,
     *         payment_usd: float,
     *         payment_ves: float,
     *     },
     * }
     */
    public function build(
        User $cashier,
        PhysicalCashBox $physicalCashBox,
        CarbonInterface $openedAt,
        CarbonInterface $closedAt,
    ): array {
        $cashier->loadMissing('branch');
        $physicalCashBox->loadMissing('user.branch');

        $sales = $this->resolveShiftSales($cashier, $openedAt, $closedAt);

        $uniqueClients = $sales
            ->pluck('client_id')
            ->filter(fn (mixed $clientId): bool => filled($clientId))
            ->unique()
            ->count();

        $walkInSales = $sales->filter(fn (Sale $sale): bool => blank($sale->client_id))->count();

        $summary = [
            'sale_count' => $sales->count(),
            'customers_served' => $uniqueClients + $walkInSales,
            'quantity_sold' => round((float) $sales->sum(
                fn (Sale $sale): float => (float) $sale->items->sum('quantity'),
            ), 3),
            'grand_total' => round((float) $sales->sum('total'), 2),
            'payment_usd_sum' => round((float) $sales->sum('payment_usd'), 2),
            'payment_ves_sum' => round((float) $sales->sum('payment_ves'), 2),
        ];

        /** @var Collection<string, Collection<int, Sale>> $paymentGroups */
        $paymentGroups = $sales->groupBy(
            fn (Sale $sale): string => (string) (PosPaymentMethodOptions::effectiveSalePaymentMethod($sale) ?? ''),
        );

        $paymentBreakdown = [];
        foreach ($paymentGroups as $method => $group) {
            $paymentBreakdown[] = [
                'method' => (string) $method,
                'label' => PosPaymentMethodOptions::posCobroOptionLabel((string) $method) ?? '—',
                'count' => $group->count(),
                'total_document' => round((float) $group->sum('total'), 2),
                'payment_usd' => round((float) $group->sum(
                    fn (Sale $sale): float => self::resolvedPaymentAmounts($sale)['usd'],
                ), 2),
                'payment_ves' => round((float) $group->sum(
                    fn (Sale $sale): float => self::resolvedPaymentAmounts($sale)['ves'],
                ), 2),
            ];
        }

        usort(
            $paymentBreakdown,
            fn (array $a, array $b): int => $b['total_document'] <=> $a['total_document']
                ?: strcmp($a['label'], $b['label']),
        );

        $breakdownCollection = collect($paymentBreakdown);
        $paymentBreakdownTotals = [
            'count' => (int) $breakdownCollection->sum('count'),
            'total_document' => round((float) $breakdownCollection->sum('total_document'), 2),
            'payment_usd' => round((float) $breakdownCollection->sum('payment_usd'), 2),
            'payment_ves' => round((float) $breakdownCollection->sum('payment_ves'), 2),
        ];

        $timezone = (string) config('app.timezone');

        return [
            'cashier_name' => filled($cashier->name) ? (string) $cashier->name : (string) ($cashier->email ?? 'Cajero'),
            'branch_name' => (string) ($cashier->branch?->name ?? $physicalCashBox->user?->branch?->name ?? 'Sin sucursal'),
            'opened_at_label' => $openedAt->timezone($timezone)->format('d/m/Y H:i'),
            'closed_at_label' => $closedAt->timezone($timezone)->format('d/m/Y H:i'),
            'summary' => $summary,
            'payment_breakdown' => $paymentBreakdown,
            'payment_breakdown_totals' => $paymentBreakdownTotals,
        ];
    }

    /**
     * @return Collection<int, Sale>
     */
    private function resolveShiftSales(User $cashier, CarbonInterface $openedAt, CarbonInterface $closedAt): Collection
    {
        $creatorValues = BranchAuthScope::saleCreatorMatchValuesForUser($cashier);

        if ($creatorValues === []) {
            return collect();
        }

        $query = Sale::query()
            ->with(['items', 'conciliationCachea'])
            ->where('status', SaleStatus::Completed)
            ->whereIn('created_by', $creatorValues)
            ->whereRaw('COALESCE(sold_at, created_at) >= ?', [$openedAt])
            ->whereRaw('COALESCE(sold_at, created_at) <= ?', [$closedAt]);

        $this->applyBranchScope($query, $cashier);

        return $query
            ->orderByRaw('COALESCE(sold_at, created_at) ASC')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Builder<Sale>  $query
     */
    private function applyBranchScope(Builder $query, User $cashier): void
    {
        if (filled($cashier->branch_id)) {
            $query->where($query->getModel()->getTable().'.branch_id', (int) $cashier->branch_id);

            return;
        }

        $branchIds = $cashier->restrictedBranchIdsForQueries();
        if ($branchIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $column = $query->getModel()->getTable().'.branch_id';
        if (count($branchIds) === 1) {
            $query->where($column, $branchIds[0]);

            return;
        }

        $query->whereIn($column, $branchIds);
    }

    /**
     * Completa el cobro en la moneda faltante usando la tasa BCV de la venta.
     *
     * @return array{usd: float, ves: float}
     */
    private static function resolvedPaymentAmounts(Sale $sale): array
    {
        $usd = round((float) $sale->payment_usd, 2);
        $ves = round((float) $sale->payment_ves, 2);
        $rate = self::resolveVesUsdRate($sale);

        if ($usd > 0.00001 && $ves <= 0.00001) {
            $ves = round($usd * $rate, 2);
        } elseif ($ves > 0.00001 && $usd <= 0.00001) {
            $usd = round($ves / $rate, 2);
        }

        return [
            'usd' => $usd,
            'ves' => $ves,
        ];
    }

    private static function resolveVesUsdRate(Sale $sale): float
    {
        $stored = (float) ($sale->bcv_ves_per_usd ?? 0);
        if ($stored > 0) {
            return $stored;
        }

        $totalUsd = (float) $sale->total;
        $ves = (float) ($sale->payment_ves ?? 0);

        if ($totalUsd > 0.00001 && $ves > 0) {
            return $ves / $totalUsd;
        }

        $fallback = config('fiscal.fallback_ves_usd_rate');

        return is_numeric($fallback) && (float) $fallback > 0 ? (float) $fallback : 1.0;
    }
}
