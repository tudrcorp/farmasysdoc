<?php

namespace App\Filament\Widgets\TodaySummary;

use App\Filament\Widgets\Concerns\InteractsWithDashboardBranchFilter;
use App\Services\Dashboard\DashboardTodaySummaryService;
use App\Support\Filament\DashboardBranchFilter;
use App\Support\Filament\DashboardDateRangeFilter;
use App\Support\Filament\DashboardViewMode;
use Filament\Widgets\Widget;

/**
 * "Resumen de hoy": saldo de cuentas por cobrar al día de hoy y movimientos del período.
 */
class AccountsReceivableSummaryWidget extends Widget
{
    use InteractsWithDashboardBranchFilter;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.today-summary.accounts-receivable';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        return DashboardViewMode::canUseTodaySummary();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'periodLabel' => DashboardDateRangeFilter::displayLabel(),
            'branchLabel' => DashboardBranchFilter::selectedBranchLabel(),
            'summary' => app(DashboardTodaySummaryService::class)->accountsReceivableSummary(
                $this->dashboardDateFrom(),
                $this->dashboardDateTo(),
                DashboardBranchFilter::selectedBranchId(),
            ),
        ];
    }
}
