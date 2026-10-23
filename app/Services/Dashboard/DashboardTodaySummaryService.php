<?php

namespace App\Services\Dashboard;

use App\Enums\SaleStatus;
use App\Filament\Resources\AccountsPayables\AccountsPayableResource;
use App\Filament\Resources\AccountsReceivables\AccountsReceivableResource;
use App\Models\AccountsPayable;
use App\Models\AccountsReceivable;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use App\Support\Finance\AccountsPayableStatus;
use App\Support\Finance\AccountsReceivableStatus;
use App\Support\Sales\SaleCollectedMoneyAggregator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Datos de la vista "Resumen de hoy" del dashboard: ventas del período, cuentas por pagar y por cobrar.
 * Los saldos pendientes y vencidos son siempre al día de hoy; el período aplica a los movimientos.
 */
final class DashboardTodaySummaryService
{
    private const int OVERDUE_LIST_LIMIT = 5;

    private const int DUE_SOON_DAYS = 7;

    public function __construct(
        private readonly SaleCollectedMoneyAggregator $collectedMoneyAggregator,
    ) {}

    /**
     * @param  list<int>  $branchIds
     * @return array{
     *     operations_count: int,
     *     general_total_usd: float,
     *     collected_usd: float,
     *     collected_ves: float,
     *     average_ticket_usd: float,
     *     branches: list<array{branch_id: int, branch_name: string, operations_count: int, general_total_usd: float}>,
     * }
     */
    public function salesSummary(CarbonInterface $from, CarbonInterface $to, array $branchIds): array
    {
        if ($branchIds === []) {
            return [
                'operations_count' => 0,
                'general_total_usd' => 0.0,
                'collected_usd' => 0.0,
                'collected_ves' => 0.0,
                'average_ticket_usd' => 0.0,
                'branches' => [],
            ];
        }

        $sales = Sale::query()
            ->excludingInternalBranchTransfers()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sold_at', [$from, $to])
            ->with(['conciliationCachea', 'posTerminal'])
            ->get();

        $collected = $this->collectedMoneyAggregator->collectedTotals($sales);
        $operationsCount = $sales->count();
        $generalTotalUsd = round((float) $sales->sum('total'), 2);
        $salesByBranch = $sales->groupBy('branch_id');

        $branches = Branch::query()
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static function (Branch $branch) use ($salesByBranch): array {
                /** @var Collection<int, Sale> $branchSales */
                $branchSales = $salesByBranch->get($branch->id, collect());

                return [
                    'branch_id' => (int) $branch->id,
                    'branch_name' => (string) $branch->name,
                    'operations_count' => $branchSales->count(),
                    'general_total_usd' => round((float) $branchSales->sum('total'), 2),
                ];
            })
            ->values()
            ->all();

        return [
            'operations_count' => $operationsCount,
            'general_total_usd' => $generalTotalUsd,
            'collected_usd' => $collected['usd'],
            'collected_ves' => $collected['ves'],
            'average_ticket_usd' => $operationsCount > 0 ? round($generalTotalUsd / $operationsCount, 2) : 0.0,
            'branches' => $branches,
        ];
    }

    /**
     * @return array{
     *     pending_count: int,
     *     pending_usd: float,
     *     pending_ves: float,
     *     overdue_count: int,
     *     overdue_usd: float,
     *     due_soon_count: int,
     *     due_soon_usd: float,
     *     issued_count: int,
     *     issued_usd: float,
     *     settled_count: int,
     *     settled_usd: float,
     *     overdue_items: list<array{name: string, document: string|null, due_at: string, days_overdue: int, amount_usd: float, url: string|null}>,
     * }
     */
    public function accountsPayableSummary(CarbonInterface $from, CarbonInterface $to, ?int $selectedBranchId): array
    {
        $base = fn (): Builder => $this->scopedQuery(AccountsPayable::query(), $selectedBranchId);
        $pending = fn (): Builder => $base()->where('status', AccountsPayableStatus::POR_PAGAR);
        $today = now()->startOfDay();

        $paid = $base()
            ->where('status', AccountsPayableStatus::PAGADO)
            ->whereBetween('paid_at', [$from, $to]);

        $canOpen = $this->canOpenMenu('accounts_payable');

        return [
            ...$this->pendingTotals($pending, $today),
            ...$this->issuedTotals($base, $from, $to, 'purchase_total_usd', AccountsPayableStatus::ANULADA),
            'settled_count' => (clone $paid)->count(),
            'settled_usd' => round((float) (clone $paid)->sum('purchase_total_usd'), 2),
            'overdue_items' => $pending()
                ->whereDate('due_at', '<', $today)
                ->orderBy('due_at')
                ->limit(self::OVERDUE_LIST_LIMIT)
                ->get()
                ->map(fn (AccountsPayable $payable): array => [
                    'name' => filled($payable->supplier_name) ? (string) $payable->supplier_name : 'Proveedor sin nombre',
                    'document' => filled($payable->supplier_invoice_number) ? 'Factura '.$payable->supplier_invoice_number : null,
                    'due_at' => $payable->due_at->format('d/m/Y'),
                    'days_overdue' => (int) $payable->due_at->startOfDay()->diffInDays($today),
                    'amount_usd' => round((float) $payable->remaining_principal_usd, 2),
                    'url' => $canOpen ? AccountsPayableResource::getUrl('view', ['record' => $payable]) : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     pending_count: int,
     *     pending_usd: float,
     *     pending_ves: float,
     *     overdue_count: int,
     *     overdue_usd: float,
     *     due_soon_count: int,
     *     due_soon_usd: float,
     *     issued_count: int,
     *     issued_usd: float,
     *     overdue_items: list<array{name: string, document: string|null, due_at: string, days_overdue: int, amount_usd: float, url: string|null}>,
     * }
     */
    public function accountsReceivableSummary(CarbonInterface $from, CarbonInterface $to, ?int $selectedBranchId): array
    {
        $base = fn (): Builder => $this->scopedQuery(AccountsReceivable::query(), $selectedBranchId);
        $pending = fn (): Builder => $base()->where('status', AccountsReceivableStatus::POR_COBRAR);
        $today = now()->startOfDay();
        $canOpen = $this->canOpenMenu('accounts_receivable');

        return [
            ...$this->pendingTotals($pending, $today),
            ...$this->issuedTotals($base, $from, $to, 'sale_total_usd', AccountsReceivableStatus::CANCELADO),
            'overdue_items' => $pending()
                ->whereDate('due_at', '<', $today)
                ->orderBy('due_at')
                ->limit(self::OVERDUE_LIST_LIMIT)
                ->get()
                ->map(fn (AccountsReceivable $receivable): array => [
                    'name' => filled($receivable->client_name_snapshot) ? (string) $receivable->client_name_snapshot : 'Cliente sin nombre',
                    'document' => filled($receivable->sale_number_snapshot) ? 'Venta '.$receivable->sale_number_snapshot : null,
                    'due_at' => $receivable->due_at->format('d/m/Y'),
                    'days_overdue' => (int) $receivable->due_at->startOfDay()->diffInDays($today),
                    'amount_usd' => round((float) $receivable->remaining_principal_usd, 2),
                    'url' => $canOpen ? AccountsReceivableResource::getUrl('view', ['record' => $receivable]) : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  callable(): Builder  $pending
     * @return array{pending_count: int, pending_usd: float, pending_ves: float, overdue_count: int, overdue_usd: float, due_soon_count: int, due_soon_usd: float}
     */
    private function pendingTotals(callable $pending, CarbonInterface $today): array
    {
        $overdue = $pending()->whereDate('due_at', '<', $today);
        $dueSoon = $pending()->whereBetween('due_at', [$today, $today->copy()->addDays(self::DUE_SOON_DAYS)->endOfDay()]);

        return [
            'pending_count' => $pending()->count(),
            'pending_usd' => round((float) $pending()->sum('remaining_principal_usd'), 2),
            'pending_ves' => round((float) $pending()->sum('current_balance_ves'), 2),
            'overdue_count' => (clone $overdue)->count(),
            'overdue_usd' => round((float) (clone $overdue)->sum('remaining_principal_usd'), 2),
            'due_soon_count' => (clone $dueSoon)->count(),
            'due_soon_usd' => round((float) (clone $dueSoon)->sum('remaining_principal_usd'), 2),
        ];
    }

    /**
     * @param  callable(): Builder  $base
     * @return array{issued_count: int, issued_usd: float}
     */
    private function issuedTotals(callable $base, CarbonInterface $from, CarbonInterface $to, string $totalColumn, string $voidStatus): array
    {
        $issued = $base()
            ->where('status', '!=', $voidStatus)
            ->whereBetween('issued_at', [$from->copy()->startOfDay(), $to]);

        return [
            'issued_count' => (clone $issued)->count(),
            'issued_usd' => round((float) (clone $issued)->sum($totalColumn), 2),
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scopedQuery(Builder $query, ?int $selectedBranchId): Builder
    {
        BranchAuthScope::apply($query);

        if ($selectedBranchId !== null) {
            $query->where($query->qualifyColumn('branch_id'), $selectedBranchId);
        }

        return $query;
    }

    private function canOpenMenu(string $menuKey): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->canAccessFarmaadminMenuKey($menuKey);
    }
}
