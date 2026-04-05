<?php

namespace App\Filament\Pages;

use App\Support\Filament\FarmaadminDeliveryUserAccess;
use Filament\Pages\Dashboard;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

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

        return parent::getWidgets();
    }
}
