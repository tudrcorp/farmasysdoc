<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\Filament\DashboardBranchFilter;
use App\Support\Filament\DashboardDateRangeFilter;
use App\Support\Filament\DashboardViewMode;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Saludo según la hora + selectores de sucursal y rango de fechas del dashboard. Estilos: `theme.css` → `.fi-farmaadmin-account-widget`.
 */
class FarmaadminAccountWidget extends Widget
{
    /**
     * Duración mínima de la transición "entrar a sucursal / período" (administradores y gerentes).
     */
    public const int BRANCH_TRANSITION_DURATION_MS = 1000;

    protected static ?int $sort = -3;

    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public string $selectedDashboardBranchKey = 'all';

    public string $dashboardDateFrom = '';

    public string $dashboardDateTo = '';

    /**
     * @var view-string
     */
    protected string $view = 'filament.farmaadmin.widgets.ios-account-widget';

    public function mount(): void
    {
        $this->selectedDashboardBranchKey = DashboardBranchFilter::selectedBranchKey();
        $this->syncDashboardDateRange();
    }

    public function applyDashboardDateRange(?string $from = null, ?string $to = null): void
    {
        if (! DashboardDateRangeFilter::canPickRange()) {
            return;
        }

        DashboardDateRangeFilter::setRange($from, $to);
        $this->syncDashboardDateRange();

        $this->dispatch('dashboard-date-range-changed');
    }

    /**
     * Alterna entre la vista general y "Resumen de hoy": al entrar, el período pasa a hoy;
     * al volver, sucursal y período regresan a sus valores por defecto.
     */
    public function toggleTodaySummary(): void
    {
        if (! DashboardViewMode::canUseTodaySummary()) {
            return;
        }

        if (DashboardViewMode::isTodaySummary()) {
            DashboardViewMode::set(DashboardViewMode::OVERVIEW);
            DashboardBranchFilter::setSelectedBranchId(null);
            DashboardDateRangeFilter::reset();
            $this->selectedDashboardBranchKey = DashboardBranchFilter::selectedBranchKey();
            $this->syncDashboardDateRange();
        } else {
            $today = now()->format('Y-m-d');

            DashboardDateRangeFilter::setRange($today, $today);
            $this->syncDashboardDateRange();
            DashboardViewMode::set(DashboardViewMode::TODAY_SUMMARY);
        }

        $this->dispatch('dashboard-view-changed');
    }

    /**
     * @return 'morning'|'afternoon'|'night'
     */
    public static function greetingPeriodFor(CarbonInterface $moment): string
    {
        return match (true) {
            $moment->hour >= 5 && $moment->hour < 12 => 'morning',
            $moment->hour >= 12 && $moment->hour < 19 => 'afternoon',
            default => 'night',
        };
    }

    private function syncDashboardDateRange(): void
    {
        $this->dashboardDateFrom = DashboardDateRangeFilter::fromKey();
        $this->dashboardDateTo = DashboardDateRangeFilter::toKey();
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
     *     showBranchTransition: bool,
     *     branchTransitionDurationMs: int,
     *     branchOptions: list<array{id: int, name: string, short_name: string}>,
     *     showDateRangePicker: bool,
     *     dateRangeLabel: string,
     *     dateRangeIsDefault: bool,
     *     dateRangePresets: list<array{key: string, label: string, from: string, to: string}>,
     *     todayKey: string,
     *     maxRangeDays: int,
     *     greetingPeriod: string,
     *     showTodaySummaryButton: bool,
     *     isTodaySummary: bool,
     *     todayLabel: string,
     * }
     */
    protected function getViewData(): array
    {
        $user = Filament::auth()->user();
        $canUseFilters = $user instanceof User && ($user->isAdministrator() || $user->isManager());
        $showBranchPicker = $canUseFilters && DashboardBranchFilter::shouldShowBranchPicker();
        $showDateRangePicker = DashboardDateRangeFilter::canPickRange();
        $now = now();

        return [
            'user' => $user,
            'showBranchPicker' => $showBranchPicker,
            'showBranchTransition' => ($showBranchPicker || $showDateRangePicker)
                && $user instanceof User
                && ($user->isAdministrator() || $user->isManager()),
            'branchTransitionDurationMs' => self::BRANCH_TRANSITION_DURATION_MS,
            'branchOptions' => DashboardBranchFilter::branchOptionsForPicker(),
            'showDateRangePicker' => $showDateRangePicker,
            'dateRangeLabel' => DashboardDateRangeFilter::label(),
            'dateRangeIsDefault' => DashboardDateRangeFilter::isMonthToDate(),
            'dateRangePresets' => DashboardDateRangeFilter::presetsForPicker(),
            'todayKey' => $now->format('Y-m-d'),
            'maxRangeDays' => DashboardDateRangeFilter::MAX_RANGE_DAYS,
            'greetingPeriod' => self::greetingPeriodFor($now),
            'showTodaySummaryButton' => DashboardViewMode::canUseTodaySummary(),
            'isTodaySummary' => DashboardViewMode::isTodaySummary(),
            'todayLabel' => ucfirst($now->locale('es')->translatedFormat('l, j \\d\\e F')),
        ];
    }
}
