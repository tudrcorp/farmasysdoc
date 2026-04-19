<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SalesChart;
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

        if ($user instanceof User && ! $user->isAdministrator()) {
            return [
                SalesChart::class,
            ];
        }

        return parent::getWidgets();
    }
}
