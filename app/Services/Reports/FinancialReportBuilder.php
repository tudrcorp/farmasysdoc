<?php

namespace App\Services\Reports;

use App\Enums\ConvenioType;
use App\Enums\FinancialFixedExpenseCategory;
use App\Enums\OrderStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\AccountsReceivable;
use App\Models\Branch;
use App\Models\FinancialFixedExpense;
use App\Models\Order;
use App\Models\PayrollLine;
use App\Models\Purchase;
use App\Models\PurchaseBook;
use App\Models\PurchaseHistory;
use App\Models\Sale;
use App\Models\User;
use App\Services\Finance\PettyCashApprovedDisbursementTotals;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Filament\BranchAuthScope;
use App\Support\Finance\AccountsReceivableStatus;
use App\Support\Finance\PettyCashMoney;
use App\Support\Purchases\PurchaseHistoryEntryType;
use App\Support\Sales\SaleCollectedMoneyAggregator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Consolida ingresos y egresos del reporte financiero para un rango de fechas.
 *
 * USD en ingresos = total facturado (documento). Bs. en ingresos = cobro físico en caja.
 * Egresos devengados (compras recibidas + nómina + fijos + caja chica aprobada) vs desembolsos CxP (flujo).
 */
final class FinancialReportBuilder
{
    public function __construct(
        private readonly SaleCollectedMoneyAggregator $collectedMoneyAggregator,
        private readonly VenezuelaOfficialUsdVesRateClient $usdVesRateClient,
        private readonly PettyCashApprovedDisbursementTotals $pettyCashApprovedDisbursementTotals,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, CarbonInterface|string $from, CarbonInterface|string $until, ?int $branchId = null): array
    {
        $fromDay = Carbon::parse($from)->startOfDay();
        $untilDay = Carbon::parse($until)->endOfDay();
        $branchId = $this->normalizeBranchId($user, $branchId);

        $counterSales = $this->counterSalesTotals($user, $fromDay, $untilDay, $branchId);
        $corporateSales = $this->corporateSalesTotals($user, $fromDay, $untilDay, $branchId);
        $accountsReceivable = $this->accountsReceivableCollectedTotals($user, $fromDay, $untilDay, $branchId);
        $paymentChannels = $this->counterSalesPaymentChannels($user, $fromDay, $untilDay, $branchId);
        $purchases = $this->purchaseTotals($user, $fromDay, $untilDay, $branchId);
        $accountsPayable = $this->accountsPayablePaidTotals($user, $fromDay, $untilDay, $branchId);
        $seniatRetentions = $this->seniatRetentionTotals($user, $fromDay, $untilDay, $branchId);
        $payroll = $this->payrollTotals($user, $fromDay, $untilDay, $branchId);
        $fixedServices = $this->fixedServiceExpenseTotals($user, $fromDay, $untilDay, $branchId);
        $pettyCash = $this->pettyCashTotals($user, $fromDay, $untilDay, $branchId);

        $incomeUsd = round(
            $counterSales['usd'] + $corporateSales['usd'] + $accountsReceivable['usd'],
            2,
        );
        $incomeVesCollected = round(
            $counterSales['ves'] + $corporateSales['ves'] + $accountsReceivable['ves'],
            2,
        );
        $incomeCount = $counterSales['count'] + $corporateSales['count'] + $accountsReceivable['count'];

        $fixedUsd = round($payroll['usd'] + $fixedServices['usd'], 2);
        $fixedVes = round($payroll['ves'] + $fixedServices['ves'], 2);

        $accrualExpenseUsd = round($purchases['usd'] + $fixedUsd + $pettyCash['usd'], 2);
        $accrualExpenseVes = round($purchases['ves'] + $fixedVes + $pettyCash['ves'], 2);
        $accrualExpenseCount = $purchases['count'] + $payroll['count'] + $fixedServices['count'] + $pettyCash['count'];

        $cashDisbursementUsd = round($accountsPayable['usd'] + $fixedUsd + $pettyCash['usd'], 2);
        $cashDisbursementVes = round($accountsPayable['ves'] + $fixedVes + $pettyCash['ves'], 2);
        $cashDisbursementCount = $accountsPayable['count'] + $payroll['count'] + $fixedServices['count'] + $pettyCash['count'];

        $linker = new FinancialReportDrillDownLinker(
            $fromDay->toDateString(),
            $untilDay->toDateString(),
            $branchId,
        );

        return [
            'period' => [
                'from' => $fromDay->toDateString(),
                'until' => $untilDay->toDateString(),
                'label' => $fromDay->format('d/m/Y').' — '.$untilDay->format('d/m/Y'),
            ],
            'filters' => [
                'branch_id' => $branchId,
                'branch_label' => $this->branchLabel($branchId),
            ],
            'columns' => [
                'usd_label' => 'USD (facturado / devengado)',
                'ves_label' => 'Bs. (cobrado / pagado)',
            ],
            'income' => [
                'counter_sales' => [
                    'count' => $counterSales['count'],
                    'usd' => $counterSales['usd'],
                    'ves' => $counterSales['ves'],
                    'usd_collected' => $counterSales['usd_collected'],
                    'label' => 'Ventas por mostrador',
                    'meta' => 'USD: total facturado · Bs.: cobrado en caja',
                    'drill_down' => $linker->salesIndex(),
                ],
                'corporate_sales' => [
                    'count' => $corporateSales['count'],
                    'usd' => $corporateSales['usd'],
                    'ves' => $corporateSales['ves'],
                    'label' => 'Ventas corporativas',
                    'meta' => 'Pedidos aliados o convenio corporativo completados',
                    'drill_down' => $linker->ordersIndex(),
                ],
                'accounts_receivable' => [
                    'count' => $accountsReceivable['count'],
                    'usd' => $accountsReceivable['usd'],
                    'ves' => $accountsReceivable['ves'],
                    'label' => 'CxC cobradas',
                    'meta' => 'Cobros posteriores a la venta a crédito',
                    'drill_down' => $linker->accountsReceivableIndex(),
                ],
                'payment_channels' => $paymentChannels,
                'total' => [
                    'count' => $incomeCount,
                    'usd' => $incomeUsd,
                    'ves' => $incomeVesCollected,
                ],
            ],
            'expenses' => [
                'purchases' => [
                    'count' => $purchases['count'],
                    'usd' => $purchases['usd'],
                    'ves' => $purchases['ves'],
                    'label' => 'Compras recibidas',
                    'meta' => 'Facturas recibidas en el período (devengado)',
                    'drill_down' => $linker->purchasesIndex(),
                ],
                'seniat_retentions' => [
                    'count' => $seniatRetentions['count'],
                    'usd' => $seniatRetentions['usd'],
                    'ves' => $seniatRetentions['ves'],
                    'label' => 'Retenciones IVA (SENIAT)',
                    'meta' => 'Comprobantes emitidos en el período (referencial fiscal)',
                    'fiscal_only' => true,
                    'drill_down' => $linker->seniatRetentionsIndex(),
                ],
                'accounts_payable' => [
                    'count' => $accountsPayable['count'],
                    'usd' => $accountsPayable['usd'],
                    'ves' => $accountsPayable['ves'],
                    'label' => 'CxP pagadas',
                    'meta' => 'Desembolsos a proveedores (flujo de caja)',
                    'cash_flow_only' => true,
                    'drill_down' => $linker->purchaseHistoriesIndex(),
                ],
                'fixed' => [
                    'payroll' => [
                        'count' => $payroll['count'],
                        'usd' => $payroll['usd'],
                        'ves' => $payroll['ves'],
                        'label' => 'Sueldos y salarios (nómina)',
                        'meta' => $branchId === null && ! $user->isAdministrator()
                            ? 'Prorrateado por sucursal del empleado'
                            : 'Según líneas de nómina del período',
                        'drill_down' => $linker->payrollIndex(),
                    ],
                    'services' => [
                        'count' => $fixedServices['count'],
                        'usd' => $fixedServices['usd'],
                        'ves' => $fixedServices['ves'],
                        'label' => 'Servicios fijos',
                        'by_category' => $fixedServices['by_category'],
                    ],
                    'total' => [
                        'usd' => $fixedUsd,
                        'ves' => $fixedVes,
                    ],
                ],
                'variable' => [
                    'petty_cash' => [
                        'count' => $pettyCash['count'],
                        'usd' => $pettyCash['usd'],
                        'ves' => $pettyCash['ves'],
                        'label' => 'Caja chica',
                        'meta' => 'Fondos aprobados en el período (incl. agotadas y cerradas por reposición)',
                        'drill_down' => $linker->pettyCashIndex(),
                    ],
                ],
                'total_accrual' => [
                    'count' => $accrualExpenseCount,
                    'usd' => $accrualExpenseUsd,
                    'ves' => $accrualExpenseVes,
                    'label' => 'Total egresos (devengado)',
                ],
                'total_cash' => [
                    'count' => $cashDisbursementCount,
                    'usd' => $cashDisbursementUsd,
                    'ves' => $cashDisbursementVes,
                    'label' => 'Total desembolsos (flujo de caja)',
                ],
                'total' => [
                    'count' => $accrualExpenseCount,
                    'usd' => $accrualExpenseUsd,
                    'ves' => $accrualExpenseVes,
                ],
            ],
            'by_branch' => $branchId === null
                ? $this->branchBreakdownRows($user, $fromDay, $untilDay)
                : [],
            'fixed_service_items' => $fixedServices['items'],
            'net' => [
                'usd' => round($incomeUsd - $accrualExpenseUsd, 2),
                'ves' => round($incomeVesCollected - $accrualExpenseVes, 2),
                'label' => 'Resultado neto (devengado)',
            ],
            'net_cash' => [
                'usd' => round($counterSales['usd_collected'] + $accountsReceivable['usd'] - $cashDisbursementUsd, 2),
                'ves' => round($incomeVesCollected - $cashDisbursementVes, 2),
                'label' => 'Resultado flujo de caja',
            ],
            'drill_down' => $linker->all(),
        ];
    }

    /**
     * @return array{count: int, usd: float, ves: float, usd_collected: float}
     */
    private function counterSalesTotals(User $user, CarbonInterface $from, CarbonInterface $until, ?int $branchId): array
    {
        $query = Sale::query()
            ->with(['conciliationCachea', 'posTerminal'])
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereBetween('sold_at', [$from, $until]);

        BranchAuthScope::applyToSalesQuery($query);
        $this->applyBranchFilter($query, $branchId);

        /** @var Collection<int, Sale> $sales */
        $sales = $query->get();
        $collected = $this->collectedMoneyAggregator->collectedTotals($sales);

        return [
            'count' => $sales->count(),
            'usd' => round((float) $sales->sum('total'), 2),
            'ves' => round($collected['ves'], 2),
            'usd_collected' => round($collected['usd'], 2),
        ];
    }

    /**
     * @return list<array{label: string, count: int, usd: float, ves: float}>
     */
    private function counterSalesPaymentChannels(User $user, CarbonInterface $from, CarbonInterface $until, ?int $branchId): array
    {
        $query = Sale::query()
            ->with(['conciliationCachea', 'posTerminal'])
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereBetween('sold_at', [$from, $until]);

        BranchAuthScope::applyToSalesQuery($query);
        $this->applyBranchFilter($query, $branchId);

        /** @var Collection<int, Sale> $sales */
        $sales = $query->get();

        return $this->collectedMoneyAggregator->collectedChannelRows($sales);
    }

    /**
     * @return array{count: int, usd: float, ves: float}
     */
    private function corporateSalesTotals(User $user, CarbonInterface $from, CarbonInterface $until, ?int $branchId): array
    {
        $query = Order::query()
            ->where('status', OrderStatus::Completed)
            ->where(function (Builder $q): void {
                $q->whereNotNull('partner_company_id')
                    ->orWhere('convenio_type', ConvenioType::Corporate->value);
            })
            ->whereRaw('COALESCE(delivered_at, created_at) BETWEEN ? AND ?', [$from, $until]);

        BranchAuthScope::applyToOrdersTableQuery($query);
        $this->applyBranchFilter($query, $branchId);

        $orders = $query->get(['id', 'total', 'delivered_at', 'created_at']);

        $usd = 0.0;
        $ves = 0.0;

        foreach ($orders as $order) {
            $total = (float) $order->total;
            $effectiveDate = $order->delivered_at ?? $order->created_at;
            $rate = $this->usdVesRateClient->rateForDate($effectiveDate) ?? 0.0;

            $usd += $total;
            if ($rate > 0) {
                $ves += round($total * $rate, 2);
            }
        }

        return [
            'count' => $orders->count(),
            'usd' => round($usd, 2),
            'ves' => round($ves, 2),
        ];
    }

    /**
     * @return array{count: int, usd: float, ves: float}
     */
    private function accountsReceivableCollectedTotals(User $user, CarbonInterface $from, CarbonInterface $until, ?int $branchId): array
    {
        $query = AccountsReceivable::query()
            ->where('status', AccountsReceivableStatus::COBRADO)
            ->whereBetween('updated_at', [$from, $until]);

        BranchAuthScope::apply($query);
        $this->applyBranchFilter($query, $branchId);

        $records = $query->get([
            'paid_equivalent_usd',
            'payment_usd_snapshot',
            'payment_ves_snapshot',
            'bcv_ves_per_usd_snapshot',
            'created_at',
            'updated_at',
        ])->filter(
            fn (AccountsReceivable $record): bool => $record->updated_at !== null
                && $record->created_at !== null
                && $record->updated_at->greaterThan($record->created_at->copy()->addMinute()),
        );

        $usd = 0.0;
        $ves = 0.0;

        foreach ($records as $record) {
            $usd += (float) $record->paid_equivalent_usd;
            $paidUsd = (float) $record->payment_usd_snapshot;
            $paidVes = (float) $record->payment_ves_snapshot;
            $rate = (float) ($record->bcv_ves_per_usd_snapshot ?? 0);
            $ves += $paidVes + ($rate > 0 ? round($paidUsd * $rate, 2) : 0.0);
        }

        return [
            'count' => $records->count(),
            'usd' => round($usd, 2),
            'ves' => round($ves, 2),
        ];
    }

    /**
     * @return array{count: int, usd: float, ves: float}
     */
    private function purchaseTotals(User $user, CarbonInterface $from, CarbonInterface $until, ?int $branchId): array
    {
        $query = Purchase::query()
            ->where('status', PurchaseStatus::Received)
            ->whereNotNull('supplier_invoice_date')
            ->whereBetween('supplier_invoice_date', [$from->toDateString(), $until->toDateString()]);

        BranchAuthScope::apply($query);
        $this->applyBranchFilter($query, $branchId);

        $purchases = $query->get(['id', 'total', 'official_usd_ves_rate']);

        $usd = 0.0;
        $ves = 0.0;

        foreach ($purchases as $purchase) {
            $total = (float) $purchase->total;
            $rate = (float) ($purchase->official_usd_ves_rate ?? 0);

            $usd += $total;
            if ($rate > 0) {
                $ves += round($total * $rate, 2);
            }
        }

        return [
            'count' => $purchases->count(),
            'usd' => round($usd, 2),
            'ves' => round($ves, 2),
        ];
    }

    /**
     * @return array{count: int, usd: float, ves: float}
     */
    private function seniatRetentionTotals(User $user, CarbonInterface $from, CarbonInterface $until, ?int $branchId): array
    {
        $query = PurchaseBook::query()
            ->whereNotNull('issue_date')
            ->whereBetween('issue_date', [$from->toDateString(), $until->toDateString()])
            ->where('tax_retained_ves', '>', 0);

        $this->applyPurchaseBookBranchScope($query, $user, $branchId);

        $books = $query->get(['tax_retained_ves', 'bcv_rate_at_invoice']);

        $ves = 0.0;
        $usd = 0.0;

        foreach ($books as $book) {
            $retainedVes = (float) $book->tax_retained_ves;
            $ves += $retainedVes;
            $rate = (float) ($book->bcv_rate_at_invoice ?? 0);
            if ($rate > 0) {
                $usd += round($retainedVes / $rate, 2);
            }
        }

        return [
            'count' => $books->count(),
            'usd' => round($usd, 2),
            'ves' => round($ves, 2),
        ];
    }

    /**
     * @return array{count: int, usd: float, ves: float}
     */
    private function accountsPayablePaidTotals(User $user, CarbonInterface $from, CarbonInterface $until, ?int $branchId): array
    {
        $query = PurchaseHistory::query()
            ->where('entry_type', PurchaseHistoryEntryType::PAGO_CUENTA_POR_PAGAR)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $until]);

        BranchAuthScope::apply($query);
        $this->applyBranchFilter($query, $branchId);

        $payments = $query->get(['amount_paid_usd', 'amount_paid_ves']);

        return [
            'count' => $payments->count(),
            'usd' => round((float) $payments->sum('amount_paid_usd'), 2),
            'ves' => round((float) $payments->sum('amount_paid_ves'), 2),
        ];
    }

    /**
     * @return array{count: int, usd: float, ves: float}
     */
    private function payrollTotals(User $user, CarbonInterface $from, CarbonInterface $until, ?int $branchId): array
    {
        $query = PayrollLine::query()
            ->whereHas('period', function (Builder $periodQuery) use ($from, $until): void {
                $periodQuery
                    ->whereIn('status', [PayrollPeriodStatus::Calculated, PayrollPeriodStatus::Closed])
                    ->whereBetween('period_date', [$from->toDateString(), $until->toDateString()]);
            });

        if ($branchId !== null) {
            $query->whereHas('employee', fn (Builder $employeeQuery): Builder => $employeeQuery->where('branch_id', $branchId));
        } elseif (! $user->isAdministrator() && ! $user->isDeliveryUser()) {
            $branchIds = $user->restrictedBranchIdsForQueries();
            if ($branchIds === []) {
                return ['count' => 0, 'usd' => 0.0, 'ves' => 0.0];
            }

            $query->whereHas('employee', fn (Builder $employeeQuery): Builder => $employeeQuery->whereIn('branch_id', $branchIds));
        }

        $lines = $query->get(['net_usd', 'net_ves', 'payroll_period_id']);

        return [
            'count' => $lines->pluck('payroll_period_id')->unique()->count(),
            'usd' => round((float) $lines->sum('net_usd'), 2),
            'ves' => round((float) $lines->sum('net_ves'), 2),
        ];
    }

    /**
     * @return array{
     *     count: int,
     *     usd: float,
     *     ves: float,
     *     by_category: list<array{key: string, label: string, count: int, usd: float, ves: float}>,
     *     items: list<array{
     *         id: int,
     *         paid_at: string,
     *         category: string,
     *         category_key: string,
     *         description: string,
     *         branch: string,
     *         usd: float,
     *         ves: float,
     *     }>
     * }
     */
    private function fixedServiceExpenseTotals(User $user, CarbonInterface $from, CarbonInterface $until, ?int $branchId): array
    {
        $emptyByCategory = $this->emptyFixedServiceCategoryRows();

        $query = FinancialFixedExpense::query()
            ->with(['branch:id,name'])
            ->whereBetween('paid_at', [$from, $until]);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        } elseif (! $user->isAdministrator() && ! $user->isDeliveryUser()) {
            $branchIds = $user->restrictedBranchIdsForQueries();
            if ($branchIds === []) {
                return ['count' => 0, 'usd' => 0.0, 'ves' => 0.0, 'by_category' => $emptyByCategory, 'items' => []];
            }

            $query->where(function (Builder $q) use ($branchIds): void {
                $q->whereIn('branch_id', $branchIds)->orWhereNull('branch_id');
            });
        }

        $records = $query->orderByDesc('paid_at')->get();

        $byCategory = [];
        foreach ($emptyByCategory as $row) {
            $byCategory[$row['key']] = $row;
        }

        $items = [];
        foreach ($records as $record) {
            $category = $record->category instanceof FinancialFixedExpenseCategory
                ? $record->category
                : FinancialFixedExpenseCategory::Other;
            $categoryKey = $category->value;
            $usd = (float) $record->amount_usd;
            $ves = (float) $record->amount_ves;

            $byCategory[$categoryKey]['count']++;
            $byCategory[$categoryKey]['usd'] = round($byCategory[$categoryKey]['usd'] + $usd, 2);
            $byCategory[$categoryKey]['ves'] = round($byCategory[$categoryKey]['ves'] + $ves, 2);

            $items[] = [
                'id' => (int) $record->id,
                'paid_at' => $record->paid_at?->format('d/m/Y H:i') ?? '',
                'category' => $category->label(),
                'category_key' => $categoryKey,
                'description' => (string) $record->description,
                'branch' => (string) ($record->branch?->name ?? 'Todas / corporativo'),
                'usd' => $usd,
                'ves' => $ves,
            ];
        }

        return [
            'count' => $records->count(),
            'usd' => round((float) $records->sum('amount_usd'), 2),
            'ves' => round((float) $records->sum('amount_ves'), 2),
            'by_category' => array_values($byCategory),
            'items' => $items,
        ];
    }

    /**
     * @return list<array{key: string, label: string, count: int, usd: float, ves: float}>
     */
    private function emptyFixedServiceCategoryRows(): array
    {
        $rows = [];
        foreach (FinancialFixedExpenseCategory::cases() as $case) {
            $rows[] = [
                'key' => $case->value,
                'label' => $case->label(),
                'count' => 0,
                'usd' => 0.0,
                'ves' => 0.0,
            ];
        }

        return $rows;
    }

    /**
     * @return array{count: int, usd: float, ves: float}
     */
    private function pettyCashTotals(User $user, CarbonInterface $from, CarbonInterface $until, ?int $branchId): array
    {
        return $this->pettyCashApprovedDisbursementTotals->forUserAndPeriod($user, $from, $until, $branchId);
    }

    /**
     * @return list<array{
     *     branch_id: int,
     *     branch: string,
     *     income_usd: float,
     *     income_ves: float,
     *     expense_usd: float,
     *     expense_ves: float,
     *     net_usd: float,
     *     net_ves: float,
     * }>
     */
    private function branchBreakdownRows(User $user, CarbonInterface $from, CarbonInterface $until): array
    {
        $branchQuery = Branch::query()->where('is_active', true)->orderBy('name');
        BranchAuthScope::applyToBranchFormSelect($branchQuery);
        $branches = $branchQuery->get(['id', 'name']);

        if ($branches->count() <= 1) {
            return [];
        }

        $rows = [];

        foreach ($branches as $branch) {
            $branchId = (int) $branch->id;
            $counter = $this->counterSalesTotals($user, $from, $until, $branchId);
            $corporate = $this->corporateSalesTotals($user, $from, $until, $branchId);
            $ar = $this->accountsReceivableCollectedTotals($user, $from, $until, $branchId);
            $purchases = $this->purchaseTotals($user, $from, $until, $branchId);
            $payroll = $this->payrollTotals($user, $from, $until, $branchId);
            $fixed = $this->fixedServiceExpenseTotals($user, $from, $until, $branchId);
            $petty = $this->pettyCashTotals($user, $from, $until, $branchId);

            $incomeUsd = round($counter['usd'] + $corporate['usd'] + $ar['usd'], 2);
            $incomeVes = round($counter['ves'] + $corporate['ves'] + $ar['ves'], 2);
            $expenseUsd = round($purchases['usd'] + $payroll['usd'] + $fixed['usd'] + $petty['usd'], 2);
            $expenseVes = round($purchases['ves'] + $payroll['ves'] + $fixed['ves'] + $petty['ves'], 2);

            $rows[] = [
                'branch_id' => $branchId,
                'branch' => (string) $branch->name,
                'income_usd' => $incomeUsd,
                'income_ves' => $incomeVes,
                'expense_usd' => $expenseUsd,
                'expense_ves' => $expenseVes,
                'net_usd' => round($incomeUsd - $expenseUsd, 2),
                'net_ves' => round($incomeVes - $expenseVes, 2),
            ];
        }

        return $rows;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applyBranchFilter(Builder $query, ?int $branchId, string $column = 'branch_id'): Builder
    {
        if ($branchId === null) {
            return $query;
        }

        return $query->where($query->qualifyColumn($column), $branchId);
    }

    /**
     * @param  Builder<PurchaseBook>  $query
     * @return Builder<PurchaseBook>
     */
    private function applyPurchaseBookBranchScope(Builder $query, User $user, ?int $branchId): Builder
    {
        if ($branchId !== null) {
            return $query->whereHas(
                'purchase',
                fn (Builder $purchaseQuery): Builder => $purchaseQuery->where('branch_id', $branchId),
            );
        }

        if ($user->isAdministrator() || $user->isDeliveryUser()) {
            return $query;
        }

        $branchIds = $user->restrictedBranchIdsForQueries();
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'purchase',
            fn (Builder $purchaseQuery): Builder => $purchaseQuery->whereIn('branch_id', $branchIds),
        );
    }

    private function normalizeBranchId(User $user, ?int $branchId): ?int
    {
        if ($branchId === null) {
            return null;
        }

        $allowedQuery = Branch::query()->where('is_active', true)->whereKey($branchId);
        BranchAuthScope::applyToBranchFormSelect($allowedQuery);

        return $allowedQuery->exists() ? $branchId : null;
    }

    private function branchLabel(?int $branchId): string
    {
        if ($branchId === null) {
            return 'Todas las sucursales';
        }

        return (string) (Branch::query()->whereKey($branchId)->value('name') ?? 'Sucursal');
    }

    public static function formatUsd(float $amount): string
    {
        return PettyCashMoney::formatUsd($amount);
    }

    public static function formatVes(float $amount): string
    {
        return PettyCashMoney::formatVes($amount);
    }
}
