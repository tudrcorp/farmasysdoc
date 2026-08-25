<?php

namespace App\Services\Sales;

use App\Enums\SaleStatus;
use App\Models\PhysicalCashBox;
use App\Models\PhysicalCashBoxMovement;
use App\Models\PosTerminal;
use App\Models\Sale;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use App\Support\Sales\MixedPosPaymentSupport;
use App\Support\Sales\PosPaymentMethodOptions;
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
            'close_detail' => $this->buildCloseDetail($sales, $cashier, $physicalCashBox),
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

    /**
     * @param  Collection<int, Sale>  $sales
     * @return array{
     *     sale_count: int,
     *     total_usd: float,
     *     total_ves: float,
     *     punto_venta_ves: float,
     *     pos_terminals: list<array{id: int|null, label: string, amount_ves: float}>,
     *     pago_movil_ves: float,
     *     usd_methods_total: float,
     *     ves_methods_total: float,
     * }
     */
    private function buildCloseDetail(Collection $sales, User $cashier, PhysicalCashBox $physicalCashBox): array
    {
        $totalsByTerminalId = [];
        $unassignedPosVes = 0.0;
        $pagoMovilVes = 0.0;
        $usdMethodsTotal = 0.0;
        $vesMethodsTotal = 0.0;
        $totalUsd = 0.0;
        $totalVes = 0.0;

        foreach ($sales as $sale) {
            $resolved = self::resolvedPaymentAmounts($sale);
            $totalUsd = round($totalUsd + $resolved['usd'], 2);
            $totalVes = round($totalVes + $resolved['ves'], 2);

            $attributed = $this->attributeSalePaymentDetail($sale, $resolved);
            $usdMethodsTotal = round($usdMethodsTotal + $attributed['usd'], 2);
            $vesMethodsTotal = round($vesMethodsTotal + $attributed['other_ves'], 2);
            $pagoMovilVes = round($pagoMovilVes + $attributed['pago_movil_ves'], 2);

            if ($attributed['pos_ves'] <= 0.00001) {
                continue;
            }

            $terminalId = $attributed['pos_terminal_id'];
            if ($terminalId === null) {
                $unassignedPosVes = round($unassignedPosVes + $attributed['pos_ves'], 2);

                continue;
            }

            $totalsByTerminalId[$terminalId] = round(($totalsByTerminalId[$terminalId] ?? 0.0) + $attributed['pos_ves'], 2);
        }

        $branchId = filled($cashier->branch_id)
            ? (int) $cashier->branch_id
            : (int) ($physicalCashBox->user?->branch_id ?? 0);

        $terminals = PosTerminal::query()
            ->when(
                $branchId > 0,
                fn ($query) => $query->where('branch_id', $branchId),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->orderBy('bank_code')
            ->orderBy('code')
            ->get();

        $usedTerminalIds = array_keys($totalsByTerminalId);
        $missingIds = array_values(array_diff($usedTerminalIds, $terminals->modelKeys()));
        if ($missingIds !== []) {
            $terminals = $terminals->concat(
                PosTerminal::query()->whereIn('id', $missingIds)->get()
            );
        }

        $bankNameCounts = [];
        foreach ($terminals as $terminal) {
            $bankName = $terminal->bank()?->bankName() ?? (string) $terminal->bank_code;
            $bankNameCounts[$bankName] = ($bankNameCounts[$bankName] ?? 0) + 1;
        }

        $posTerminals = [];
        foreach ($terminals as $terminal) {
            $bankName = $terminal->bank()?->bankName() ?? (string) $terminal->bank_code;
            $label = ($bankNameCounts[$bankName] ?? 0) > 1
                ? 'POS '.$bankName.' '.$terminal->code
                : 'POS '.$bankName;

            $posTerminals[] = [
                'id' => (int) $terminal->id,
                'label' => $label,
                'amount_ves' => round((float) ($totalsByTerminalId[(int) $terminal->id] ?? 0), 2),
            ];
        }

        if ($unassignedPosVes > 0.00001) {
            $posTerminals[] = [
                'id' => null,
                'label' => 'POS sin punto asignado',
                'amount_ves' => $unassignedPosVes,
            ];
        }

        $puntoVentaVes = round((float) collect($posTerminals)->sum('amount_ves'), 2);

        return [
            'sale_count' => $sales->count(),
            'total_usd' => $totalUsd,
            'total_ves' => $totalVes,
            'punto_venta_ves' => $puntoVentaVes,
            'pos_terminals' => $posTerminals,
            'pago_movil_ves' => $pagoMovilVes,
            'usd_methods_total' => $usdMethodsTotal,
            'ves_methods_total' => $vesMethodsTotal,
        ];
    }

    /**
     * @param  array{usd: float, ves: float}  $resolved
     * @return array{pos_ves: float, pos_terminal_id: int|null, pago_movil_ves: float, usd: float, other_ves: float}
     */
    private function attributeSalePaymentDetail(Sale $sale, array $resolved): array
    {
        $method = (string) (PosPaymentMethodOptions::effectiveSalePaymentMethod($sale) ?? '');
        $terminalId = filled($sale->pos_terminal_id) ? (int) $sale->pos_terminal_id : null;

        if ($method === 'punto_venta_ves') {
            return [
                'pos_ves' => $resolved['ves'],
                'pos_terminal_id' => $terminalId,
                'pago_movil_ves' => 0.0,
                'usd' => 0.0,
                'other_ves' => 0.0,
            ];
        }

        if ($method === 'pago_movil') {
            return [
                'pos_ves' => 0.0,
                'pos_terminal_id' => null,
                'pago_movil_ves' => $resolved['ves'],
                'usd' => 0.0,
                'other_ves' => 0.0,
            ];
        }

        if (in_array($method, ['efectivo_usd', 'zelle', 'transfer_usd'], true)) {
            return [
                'pos_ves' => 0.0,
                'pos_terminal_id' => null,
                'pago_movil_ves' => 0.0,
                'usd' => $resolved['usd'],
                'other_ves' => 0.0,
            ];
        }

        if (in_array($method, ['efectivo_ves', 'transfer_ves'], true)) {
            return [
                'pos_ves' => 0.0,
                'pos_terminal_id' => null,
                'pago_movil_ves' => 0.0,
                'usd' => 0.0,
                'other_ves' => $resolved['ves'],
            ];
        }

        if ($method === 'mixed') {
            return $this->attributeMixedSalePaymentDetail($sale, $resolved, $terminalId);
        }

        if ($method === PosPaymentMethodOptions::CACHEA) {
            return $this->attributeCacheaSalePaymentDetail($sale, $resolved, $terminalId);
        }

        return [
            'pos_ves' => 0.0,
            'pos_terminal_id' => $terminalId,
            'pago_movil_ves' => 0.0,
            'usd' => $resolved['usd'],
            'other_ves' => $resolved['ves'],
        ];
    }

    /**
     * @param  array{usd: float, ves: float}  $resolved
     * @return array{pos_ves: float, pos_terminal_id: int|null, pago_movil_ves: float, usd: float, other_ves: float}
     */
    private function attributeMixedSalePaymentDetail(Sale $sale, array $resolved, ?int $terminalId): array
    {
        $split = MixedPosPaymentSupport::vesSplitAmountsFromSaleNotes($sale->notes);
        if ($split !== null) {
            return [
                'pos_ves' => $split['punto_venta_ves'],
                'pos_terminal_id' => $split['punto_venta_ves'] > 0.00001 ? $terminalId : null,
                'pago_movil_ves' => $split['pago_movil'],
                'usd' => $resolved['usd'],
                'other_ves' => round($split['efectivo_ves'] + $split['transfer_ves'], 2),
            ];
        }

        $reference = (string) ($sale->reference ?? '');
        $vesLooksLikePos = $terminalId !== null || str_contains($reference, 'POS');

        return [
            'pos_ves' => $vesLooksLikePos ? $resolved['ves'] : 0.0,
            'pos_terminal_id' => $vesLooksLikePos ? $terminalId : null,
            'pago_movil_ves' => (! $vesLooksLikePos && $this->referenceLooksLikePagoMovil($reference))
                ? $resolved['ves']
                : 0.0,
            'usd' => $resolved['usd'],
            'other_ves' => (! $vesLooksLikePos && ! $this->referenceLooksLikePagoMovil($reference))
                ? $resolved['ves']
                : 0.0,
        ];
    }

    /**
     * @param  array{usd: float, ves: float}  $resolved
     * @return array{pos_ves: float, pos_terminal_id: int|null, pago_movil_ves: float, usd: float, other_ves: float}
     */
    private function attributeCacheaSalePaymentDetail(Sale $sale, array $resolved, ?int $terminalId): array
    {
        $cachea = $sale->conciliationCachea;
        $cacheaPaid = round((float) ($cachea?->cachea_paid_amount ?? $resolved['usd']), 2);
        $complement = (string) ($cachea?->complement_payment_method ?? '');
        $remainderUsd = round((float) ($cachea?->remainder ?? 0), 2);
        $rate = self::resolveVesUsdRate($sale);
        $complementVes = round($remainderUsd * $rate, 2);

        $posVes = 0.0;
        $pmVes = 0.0;
        $usd = $cacheaPaid;
        $otherVes = 0.0;

        if ($complement === 'punto_venta_ves') {
            $posVes = $complementVes;
        } elseif ($complement === 'pago_movil') {
            $pmVes = $complementVes;
        } elseif (in_array($complement, ['efectivo_ves', 'transfer_ves'], true)) {
            $otherVes = $complementVes;
        } elseif (in_array($complement, ['efectivo_usd', 'zelle'], true)) {
            $usd = round($usd + $remainderUsd, 2);
        }

        return [
            'pos_ves' => $posVes,
            'pos_terminal_id' => $posVes > 0.00001 ? $terminalId : null,
            'pago_movil_ves' => $pmVes,
            'usd' => $usd,
            'other_ves' => $otherVes,
        ];
    }

    private function referenceLooksLikePagoMovil(string $reference): bool
    {
        $normalized = mb_strtolower(trim($reference));

        return $normalized !== ''
            && (str_contains($normalized, 'pago movil')
                || str_contains($normalized, 'pago_movil')
                || preg_match('/^\d{4,12}$/', $normalized) === 1);
    }
}
