<?php

namespace App\Services\Branches;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\BranchDailyOperation;
use App\Models\Sale;
use App\Support\Sales\SaleCollectedMoneyAggregator;

final class BranchDailyOperationReconciliationBuilder
{
    public function __construct(
        private readonly SaleCollectedMoneyAggregator $collectedMoneyAggregator,
    ) {}

    /**
     * @return array{
     *     branch_name: string,
     *     opened_at_label: string,
     *     closed_at_label: string,
     *     closed_by_name: string,
     *     sale_count: int,
     *     total_usd: float,
     *     total_ves: float,
     *     pago_movil_ves: float,
     *     punto_venta_ves: float,
     *     pos_terminals: list<array{id: int|null, label: string, amount_ves: float}>,
     *     transfer_ves: float,
     *     transfer_usd: float,
     *     efectivo_ves: float,
     *     efectivo_usd: float,
     * }
     */
    public function build(Branch $branch, BranchDailyOperation $operation): array
    {
        $openedAt = $operation->opened_at ?? now();
        $closedAt = $operation->closed_at ?? now();
        $timezone = (string) config('app.timezone');

        $operation->loadMissing('closedBy');

        $sales = Sale::query()
            ->with(['posTerminal', 'conciliationCachea'])
            ->excludingInternalBranchTransfers()
            ->where('status', SaleStatus::Completed)
            ->where('branch_id', $branch->getKey())
            ->whereRaw('COALESCE(sold_at, created_at) >= ?', [$openedAt])
            ->whereRaw('COALESCE(sold_at, created_at) <= ?', [$closedAt])
            ->orderByRaw('COALESCE(sold_at, created_at) ASC')
            ->orderBy('id')
            ->get();

        $totals = $this->collectedMoneyAggregator->closeTotals($sales, (int) $branch->getKey());
        $closedBy = $operation->closedBy;

        return [
            'branch_name' => (string) ($branch->name ?? 'Sucursal'),
            'opened_at_label' => $openedAt->timezone($timezone)->format('d/m/Y H:i'),
            'closed_at_label' => $closedAt->timezone($timezone)->format('d/m/Y H:i'),
            'closed_by_name' => filled($closedBy?->name)
                ? (string) $closedBy->name
                : (string) ($closedBy?->email ?? 'Gerencia'),
            ...$totals,
        ];
    }
}
