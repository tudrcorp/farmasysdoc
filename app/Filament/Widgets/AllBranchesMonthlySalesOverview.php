<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Dashboard\AllBranchesMonthlySalesStatsService;
use App\Support\Filament\DashboardBranchFilter;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class AllBranchesMonthlySalesOverview extends Widget
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.stats-all-branches-overview';

    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if (! $user->isManager()) {
            return false;
        }

        return DashboardBranchFilter::allowedBranchIdsForCurrentUser() !== [];
    }

    /**
     * @return array{
     *     month_label: string,
     *     registered_branches_count: int,
     *     scope_description: string,
     *     branches: list<array{
     *         branch_id: int,
     *         branch_name: string,
     *         total_usd: float,
     *         total_ves: float,
     *         ves_converted_usd: float,
     *         general_total_usd: float,
     *         goal_usd: float|null,
     *         goal_progress_percent: float|null,
     *         has_goal: bool,
     *     }>,
     * }
     */
    protected function getViewData(): array
    {
        return app(AllBranchesMonthlySalesStatsService::class)->forCurrentMonthByBranch();
    }
}
