<?php

namespace App\Services\Sales;

use App\Enums\SaleStatus;
use App\Models\PhysicalCashBox;
use App\Models\PhysicalCashBoxMovement;
use App\Models\Sale;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use App\Support\Sales\SaleCollectedMoneyAggregator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Consolida ventas del turno de caja física (desde apertura hasta cierre).
 */
final class PhysicalCashBoxShiftReportBuilder
{
    private const KIND_EFECTIVO_USD_VUELTO = 'efectivo_usd_vuelto';

    private const KIND_MIXED_EFECTIVO_VES_VUELTO = 'mixed_efectivo_ves_vuelto';

    public function __construct(
        private readonly SaleCollectedMoneyAggregator $collectedMoneyAggregator,
    ) {}

    /**
     * @param  array{
     *     expected_usd: float,
     *     expected_ves: float,
     *     declared_usd: float,
     *     declared_ves: float,
     * }|null  $reconciliationSnapshot
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
     *     close_detail: array{
     *         sale_count: int,
     *         total_usd: float,
     *         total_ves: float,
     *         punto_venta_ves: float,
     *         pos_terminals: list<array{id: int|null, label: string, amount_ves: float}>,
     *         pago_movil_ves: float,
     *         transfer_ves: float,
     *         transfer_usd: float,
     *         efectivo_ves: float,
     *         efectivo_usd: float,
     *         usd_methods_total: float,
     *         ves_methods_total: float,
     *     },
     *     cash_box_reconciliation: array{
     *         movements_count: int,
     *         opening_usd: float,
     *         opening_ves: float,
     *         inbound_client_bill_usd: float,
     *         inbound_client_bill_usd_count: int,
     *         inbound_mixed_ves: float,
     *         inbound_mixed_ves_count: int,
     *         inbound_usd_total: float,
     *         inbound_ves_total: float,
     *         outbound_drawer_usd: float,
     *         outbound_drawer_usd_count: int,
     *         outbound_change_ves: float,
     *         outbound_change_ves_count: int,
     *         outbound_usd_total: float,
     *         outbound_ves_total: float,
     *         expected_usd: float,
     *         expected_ves: float,
     *         declared_usd: float,
     *         declared_ves: float,
     *         difference_usd: float,
     *         difference_ves: float,
     *         has_mismatch: bool,
     *     },
     * }
     */
    public function build(
        User $cashier,
        PhysicalCashBox $physicalCashBox,
        CarbonInterface $openedAt,
        CarbonInterface $closedAt,
        ?array $reconciliationSnapshot = null,
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

        $collectedTotals = $this->collectedMoneyAggregator->collectedTotals($sales);

        $summary = [
            'sale_count' => $sales->count(),
            'customers_served' => $uniqueClients + $walkInSales,
            'quantity_sold' => round((float) $sales->sum(
                fn (Sale $sale): float => (float) $sale->items->sum('quantity'),
            ), 3),
            'grand_total' => round((float) $sales->sum('total'), 2),
            'payment_usd_sum' => $collectedTotals['usd'],
            'payment_ves_sum' => $collectedTotals['ves'],
        ];

        $paymentBreakdown = $this->collectedMoneyAggregator->collectedChannelRows($sales);
        $paymentBreakdownTotals = [
            'count' => $summary['sale_count'],
            'total_document' => $summary['grand_total'],
            'payment_usd' => $collectedTotals['usd'],
            'payment_ves' => $collectedTotals['ves'],
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
            'close_detail' => $this->buildCloseDetail($sales, $cashier, $physicalCashBox),
            'cachea_detail' => $this->collectedMoneyAggregator->cacheaCuotaBreakdown($sales),
            'cash_box_reconciliation' => $this->buildCashBoxReconciliation(
                $physicalCashBox,
                $openedAt,
                $closedAt,
                $reconciliationSnapshot,
            ),
        ];
    }

    /**
     * @param  array{
     *     expected_usd?: float,
     *     expected_ves?: float,
     *     declared_usd?: float,
     *     declared_ves?: float,
     * }|null  $reconciliationSnapshot
     * @return array{
     *     movements_count: int,
     *     opening_usd: float,
     *     opening_ves: float,
     *     inbound_client_bill_usd: float,
     *     inbound_client_bill_usd_count: int,
     *     inbound_mixed_ves: float,
     *     inbound_mixed_ves_count: int,
     *     inbound_usd_total: float,
     *     inbound_ves_total: float,
     *     outbound_drawer_usd: float,
     *     outbound_drawer_usd_count: int,
     *     outbound_change_ves: float,
     *     outbound_change_ves_count: int,
     *     outbound_usd_total: float,
     *     outbound_ves_total: float,
     *     expected_usd: float,
     *     expected_ves: float,
     *     declared_usd: float,
     *     declared_ves: float,
     *     difference_usd: float,
     *     difference_ves: float,
     *     has_mismatch: bool,
     * }
     */
    private function buildCashBoxReconciliation(
        PhysicalCashBox $physicalCashBox,
        CarbonInterface $openedAt,
        CarbonInterface $closedAt,
        ?array $reconciliationSnapshot,
    ): array {
        $movements = PhysicalCashBoxMovement::query()
            ->where('physical_cash_box_id', $physicalCashBox->id)
            ->where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closedAt)
            ->get();

        $inboundClientBillUsd = 0.0;
        $inboundClientBillUsdCount = 0;
        $inboundMixedVes = 0.0;
        $inboundMixedVesCount = 0;
        $outboundDrawerUsd = 0.0;
        $outboundDrawerUsdCount = 0;
        $outboundChangeVes = 0.0;
        $outboundChangeVesCount = 0;

        foreach ($movements as $movement) {
            $kind = (string) $movement->kind;

            if ($kind === self::KIND_EFECTIVO_USD_VUELTO) {
                $clientBillUsd = round((float) $movement->client_bill_usd, 2);
                $drawerOutUsd = round((float) $movement->drawer_out_usd, 2);
                $changeVes = round(abs((float) ($movement->final_change_ves ?? 0)), 2);

                if ($clientBillUsd > 0.00001) {
                    $inboundClientBillUsd = round($inboundClientBillUsd + $clientBillUsd, 2);
                    $inboundClientBillUsdCount++;
                }

                if ($drawerOutUsd > 0.00001) {
                    $outboundDrawerUsd = round($outboundDrawerUsd + $drawerOutUsd, 2);
                    $outboundDrawerUsdCount++;
                }

                if ($changeVes > 0.00001) {
                    $outboundChangeVes = round($outboundChangeVes + $changeVes, 2);
                    $outboundChangeVesCount++;
                }

                continue;
            }

            if ($kind !== self::KIND_MIXED_EFECTIVO_VES_VUELTO) {
                continue;
            }

            $meta = is_array($movement->meta) ? $movement->meta : [];
            $netVes = round((float) ($meta['net_ves_to_drawer'] ?? 0), 2);

            if ($netVes <= 0.00001) {
                $received = round((float) ($meta['ves_cash_received_total'] ?? 0), 2);
                $change = round((float) ($meta['change_ves_total'] ?? $movement->final_change_ves ?? 0), 2);
                $netVes = round(max(0.0, $received - $change), 2);
            }

            if ($netVes > 0.00001) {
                $inboundMixedVes = round($inboundMixedVes + $netVes, 2);
                $inboundMixedVesCount++;
            }
        }

        $expectedUsd = round((float) ($reconciliationSnapshot['expected_usd'] ?? $physicalCashBox->amount_usd), 2);
        $expectedVes = round((float) ($reconciliationSnapshot['expected_ves'] ?? $physicalCashBox->amount_ves), 2);
        $declaredUsd = round((float) ($reconciliationSnapshot['declared_usd'] ?? $expectedUsd), 2);
        $declaredVes = round((float) ($reconciliationSnapshot['declared_ves'] ?? $expectedVes), 2);
        $differenceUsd = round($declaredUsd - $expectedUsd, 2);
        $differenceVes = round($declaredVes - $expectedVes, 2);

        return [
            'movements_count' => $movements->count(),
            'opening_usd' => round($expectedUsd - $inboundClientBillUsd + $outboundDrawerUsd, 2),
            'opening_ves' => round($expectedVes - $inboundMixedVes + $outboundChangeVes, 2),
            'inbound_client_bill_usd' => $inboundClientBillUsd,
            'inbound_client_bill_usd_count' => $inboundClientBillUsdCount,
            'inbound_mixed_ves' => $inboundMixedVes,
            'inbound_mixed_ves_count' => $inboundMixedVesCount,
            'inbound_usd_total' => $inboundClientBillUsd,
            'inbound_ves_total' => $inboundMixedVes,
            'outbound_drawer_usd' => $outboundDrawerUsd,
            'outbound_drawer_usd_count' => $outboundDrawerUsdCount,
            'outbound_change_ves' => $outboundChangeVes,
            'outbound_change_ves_count' => $outboundChangeVesCount,
            'outbound_usd_total' => $outboundDrawerUsd,
            'outbound_ves_total' => $outboundChangeVes,
            'expected_usd' => $expectedUsd,
            'expected_ves' => $expectedVes,
            'declared_usd' => $declaredUsd,
            'declared_ves' => $declaredVes,
            'difference_usd' => $differenceUsd,
            'difference_ves' => $differenceVes,
            'has_mismatch' => abs($differenceUsd) >= 0.01 || abs($differenceVes) >= 0.01,
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
            ->with(['items', 'conciliationCachea', 'posTerminal'])
            ->excludingInternalBranchTransfers()
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
     * @param  Collection<int, Sale>  $sales
     * @return array{
     *     sale_count: int,
     *     total_usd: float,
     *     total_ves: float,
     *     punto_venta_ves: float,
     *     pos_terminals: list<array{id: int|null, label: string, amount_ves: float}>,
     *     pago_movil_ves: float,
     *     transfer_ves: float,
     *     transfer_usd: float,
     *     efectivo_ves: float,
     *     efectivo_usd: float,
     *     usd_methods_total: float,
     *     ves_methods_total: float,
     * }
     */
    private function buildCloseDetail(Collection $sales, User $cashier, PhysicalCashBox $physicalCashBox): array
    {
        $branchId = filled($cashier->branch_id)
            ? (int) $cashier->branch_id
            : (int) ($physicalCashBox->user?->branch_id ?? 0);

        $totals = $this->collectedMoneyAggregator->closeTotals($sales, $branchId > 0 ? $branchId : null);

        return [
            ...$totals,
            'usd_methods_total' => $totals['total_usd'],
            'ves_methods_total' => $totals['total_ves'],
        ];
    }
}
