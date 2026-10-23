<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AllBranchesMonthlySalesOverview;
use App\Filament\Widgets\CashierBranchGoalWidget;
use App\Filament\Widgets\CurrentMonthGlobalSalesOverview;
use App\Filament\Widgets\FarmaadminAccountWidget;
use App\Filament\Widgets\ManagementBranchSalesByMonthChart;
use App\Filament\Widgets\ManagementBranchSalesCurrentMonthDaysChart;
use App\Filament\Widgets\TodaySummary\AccountsPayableSummaryWidget;
use App\Filament\Widgets\TodaySummary\AccountsReceivableSummaryWidget;
use App\Filament\Widgets\TodaySummary\TodaySalesSummaryWidget;
use App\Models\User;
use App\Support\Filament\DashboardViewMode;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use Filament\Pages\Dashboard;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

/**
 * Inicio del panel Farmaadmin: oculto en sidebar para usuarios solo-entrega (usan homeUrl a Entregas).
 */
class FarmaadminDashboard extends Dashboard
{
    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        $user = request()->user() ?? Auth::user();

        if (DashboardViewMode::isTodaySummary() || ($user instanceof User && $user->hasGerenciaRole())) {
            return [
                'default' => 1,
                'lg' => 2,
            ];
        }

        if ($user instanceof User && ! $user->isAdministrator()) {
            return 1;
        }

        return [
            'default' => 1,
            'lg' => 2,
        ];
    }

    /**
     * El widget de cabecera cambia entre la vista general y "Resumen de hoy"; la página vuelve a renderizar sus widgets.
     */
    #[On('dashboard-view-changed')]
    public function refreshDashboardView(): void {}

    public static function shouldRegisterNavigation(): bool
    {
        if (FarmaadminDeliveryUserAccess::isRestrictedDeliveryUser()) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }

    /**
     * @return array<class-string<Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        if (FarmaadminDeliveryUserAccess::isRestrictedDeliveryUser()) {
            return [];
        }

        $user = request()->user() ?? Auth::user();

        if (DashboardViewMode::isTodaySummary()) {
            return [
                FarmaadminAccountWidget::class,
                TodaySalesSummaryWidget::class,
                AccountsPayableSummaryWidget::class,
                AccountsReceivableSummaryWidget::class,
            ];
        }

        if ($user instanceof User && $user->isManager() && ! $user->isAdministrator()) {
            $widgets = [
                FarmaadminAccountWidget::class,
                AllBranchesMonthlySalesOverview::class,
            ];

            if ($user->hasGerenciaRole()) {
                $widgets[] = ManagementBranchSalesByMonthChart::class;
                $widgets[] = ManagementBranchSalesCurrentMonthDaysChart::class;
            }

            return $widgets;
        }

        if ($user instanceof User && $user->isCoordinator() && ! $user->isAdministrator()) {
            return [
                AllBranchesMonthlySalesOverview::class,
                ManagementBranchSalesCurrentMonthDaysChart::class,
            ];
        }

        if ($user instanceof User && $user->isCashier() && ! $user->isAdministrator()) {
            return [
                FarmaadminAccountWidget::class,
                CashierBranchGoalWidget::class,
            ];
        }

        if ($user instanceof User && ! $user->isAdministrator()) {
            return [];
        }

        return [
            FarmaadminAccountWidget::class,
            CurrentMonthGlobalSalesOverview::class,
            AllBranchesMonthlySalesOverview::class,
            ManagementBranchSalesCurrentMonthDaysChart::class,
        ];
    }
}
