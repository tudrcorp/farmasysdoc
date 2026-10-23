<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\PurchaseStatus;
use App\Filament\Resources\AccountsReceivables\AccountsReceivableResource;
use App\Filament\Resources\Hr\PayrollPeriods\PayrollPeriodResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\PettyCashFunds\PettyCashFundResource;
use App\Filament\Resources\PurchaseBooks\PurchaseBookResource;
use App\Filament\Resources\PurchaseHistories\PurchaseHistoryResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Support\Purchases\PurchaseHistoryEntryType;

final class FinancialReportDrillDownLinker
{
    public function __construct(
        private readonly string $from,
        private readonly string $until,
        private readonly ?int $branchId = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return [
            'counter_sales' => $this->salesIndex(),
            'corporate_sales' => $this->ordersIndex(),
            'accounts_receivable' => $this->accountsReceivableIndex(),
            'purchases' => $this->purchasesIndex(),
            'payroll' => $this->payrollIndex(),
            'petty_cash' => $this->pettyCashIndex(),
            'accounts_payable' => $this->purchaseHistoriesIndex(),
            'seniat_retentions' => $this->seniatRetentionsIndex(),
        ];
    }

    public function salesIndex(): string
    {
        return SaleResource::getUrl('index', [
            'tableFilters' => array_filter([
                'sold_date_range' => [
                    'sold_from' => $this->from,
                    'sold_until' => $this->until,
                ],
                'branch_id' => $this->branchTableFilter(),
            ]),
        ], panel: 'farmaadmin', isAbsolute: false);
    }

    public function ordersIndex(): string
    {
        return OrderResource::getUrl('index', [
            'tableFilters' => array_filter([
                'status' => [
                    'values' => [OrderStatus::Completed->value],
                ],
                'branch_id' => $this->branchTableFilter(),
            ]),
        ], panel: 'farmaadmin', isAbsolute: false);
    }

    public function accountsReceivableIndex(): string
    {
        return AccountsReceivableResource::getUrl('index', [], panel: 'farmaadmin', isAbsolute: false);
    }

    public function purchasesIndex(): string
    {
        return PurchaseResource::getUrl('index', [
            'tableFilters' => array_filter([
                'status' => [
                    'values' => [PurchaseStatus::Received->value],
                ],
                'received' => [
                    'value' => true,
                ],
                'branch_id' => $this->branchTableFilter(),
            ]),
        ], panel: 'farmaadmin', isAbsolute: false);
    }

    public function payrollIndex(): string
    {
        return PayrollPeriodResource::getUrl('index', [], panel: 'farmaadmin', isAbsolute: false);
    }

    public function pettyCashIndex(): string
    {
        return PettyCashFundResource::getUrl('index', [
            'tableFilters' => array_filter([
                'branch_id' => $this->branchSingleFilter(),
            ]),
        ], panel: 'farmaadmin', isAbsolute: false);
    }

    public function purchaseHistoriesIndex(): string
    {
        return PurchaseHistoryResource::getUrl('index', [
            'tableFilters' => array_filter([
                'entry_type' => [
                    'value' => PurchaseHistoryEntryType::PAGO_CUENTA_POR_PAGAR,
                ],
                'branch_id' => $this->branchSingleFilter(),
            ]),
        ], panel: 'farmaadmin', isAbsolute: false);
    }

    public function seniatRetentionsIndex(): string
    {
        return PurchaseBookResource::getUrl('index', [
            'tableFilters' => [
                'issue_date_between' => [
                    'issue_from' => $this->from,
                    'issue_until' => $this->until,
                ],
            ],
        ], panel: 'farmaadmin', isAbsolute: false);
    }

    /**
     * @return array{values: list<int>}|null
     */
    private function branchTableFilter(): ?array
    {
        if ($this->branchId === null) {
            return null;
        }

        return ['values' => [$this->branchId]];
    }

    /**
     * @return array{value: int}|null
     */
    private function branchSingleFilter(): ?array
    {
        if ($this->branchId === null) {
            return null;
        }

        return ['value' => $this->branchId];
    }
}
