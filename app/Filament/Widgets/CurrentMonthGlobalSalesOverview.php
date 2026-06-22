<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Dashboard\AllBranchesMonthlySalesStatsService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class CurrentMonthGlobalSalesOverview extends Widget
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.stats-current-month-global-overview';

    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdministrator();
    }

    /**
     * @return array{
     *     month_label: string,
     *     scope_description: string,
     *     total_usd: float,
     *     total_ves: float,
     *     ves_converted_usd: float,
     *     general_total_usd: float,
     *     bcv_rate_used: float|null,
     *     goal_usd: float|null,
     *     goal_progress_percent: float|null,
     *     has_goal: bool,
     * }
     */
    protected function getViewData(): array
    {
        return app(AllBranchesMonthlySalesStatsService::class)->forCurrentMonthGlobalSummary();
    }
}
