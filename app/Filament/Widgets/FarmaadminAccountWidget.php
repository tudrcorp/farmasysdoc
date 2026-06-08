<?php

namespace App\Filament\Widgets;

use App\Support\Filament\DashboardBranchFilter;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Bienvenida + selector de sucursal del dashboard. Estilos: `theme.css` → `.fi-farmaadmin-account-widget`.
 */
class FarmaadminAccountWidget extends Widget
{
    protected static ?int $sort = -3;

    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public string $selectedDashboardBranchKey = 'all';

    /**
     * @var view-string
     */
    protected string $view = 'filament.farmaadmin.widgets.ios-account-widget';

    public function mount(): void
    {
        $this->selectedDashboardBranchKey = DashboardBranchFilter::selectedBranchKey();
    }

    public function updatedSelectedDashboardBranchKey(?string $value): void
    {
        $branchKey = filled($value) ? $value : 'all';
        $branchId = $branchKey === 'all' ? null : (int) $branchKey;

        DashboardBranchFilter::setSelectedBranchId($branchId);
        $this->selectedDashboardBranchKey = DashboardBranchFilter::selectedBranchKey();

        $this->dispatch('dashboard-branch-filter-changed', branchId: $branchId);
    }

    public static function canView(): bool
    {
        return Filament::auth()->check();
    }

    /**
     * @return array{
     *     user: ?Authenticatable,
     *     showBranchPicker: bool,
     *     branchOptions: list<array{id: int, name: string, short_name: string}>
     * }
     */
    protected function getViewData(): array
    {
        return [
            'user' => Filament::auth()->user(),
            'showBranchPicker' => DashboardBranchFilter::shouldShowBranchPicker(),
            'branchOptions' => DashboardBranchFilter::branchOptionsForPicker(),
        ];
    }
}
