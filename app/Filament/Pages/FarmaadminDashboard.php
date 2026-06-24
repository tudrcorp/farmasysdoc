<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AllBranchesMonthlySalesOverview;
use App\Filament\Widgets\CategoryProductSalesChart;
use App\Filament\Widgets\CurrentMonthGlobalSalesOverview;
use App\Filament\Widgets\DailyAverageTicketChart;
use App\Filament\Widgets\FarmaadminAccountWidget;
use App\Filament\Widgets\ManagementBranchSalesByMonthChart;
use App\Filament\Widgets\ManagementBranchSalesCurrentMonthDaysChart;
use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use Filament\Pages\Dashboard;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Support\Facades\Auth;

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

        if ($user instanceof User && $user->hasGerenciaRole()) {
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

        if ($user instanceof User && $user->isManager() && ! $user->isAdministrator()) {
            $widgets = [
                AllBranchesMonthlySalesOverview::class,
            ];

            if ($user->hasGerenciaRole()) {
                $widgets[] = ManagementBranchSalesByMonthChart::class;
                $widgets[] = ManagementBranchSalesCurrentMonthDaysChart::class;
            }

            return $widgets;
        }

        if ($user instanceof User && ! $user->isAdministrator()) {
            return [];
        }

        return [
            FarmaadminAccountWidget::class,
            CurrentMonthGlobalSalesOverview::class,
            AllBranchesMonthlySalesOverview::class,
            ManagementBranchSalesCurrentMonthDaysChart::class,
            DailyAverageTicketChart::class,
            CategoryProductSalesChart::class,
        ];
    }
}
