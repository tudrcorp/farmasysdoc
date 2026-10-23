<?php

namespace App\Filament\Widgets\TodaySummary;

use App\Filament\Widgets\Concerns\InteractsWithDashboardBranchFilter;
use App\Services\Dashboard\DashboardTodaySummaryService;
use App\Support\Filament\DashboardBranchFilter;
use App\Support\Filament\DashboardDateRangeFilter;
use App\Support\Filament\DashboardViewMode;
use Filament\Widgets\Widget;

/**
 * "Resumen de hoy": ventas y cantidad de operaciones del período, por sucursal.
 */
class TodaySalesSummaryWidget extends Widget
{
    use InteractsWithDashboardBranchFilter;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.today-summary.sales';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

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
            'summary' => app(DashboardTodaySummaryService::class)->salesSummary(
                $this->dashboardDateFrom(),
                $this->dashboardDateTo(),
                $this->dashboardBranchIdsForCharts(),
            ),
        ];
    }
}
