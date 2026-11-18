<?php

namespace App\Filament\BusinessPartners\Pages;

use App\Filament\BusinessPartners\Widgets\PartnerActivityWidget;
use App\Filament\BusinessPartners\Widgets\PartnerInsightsWidget;
use App\Filament\BusinessPartners\Widgets\PartnerOverviewWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Inicio de dale · Aliados: saludo, accesos rápidos y (con permiso «Tablero») cifras y gráficos.
 * Siempre accesible para el usuario del aliado: es la página a la que llega al entrar.
 */
class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::Home;

    protected static ?int $navigationSort = -10;

    public static function getNavigationLabel(): string
    {
        return 'Inicio';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Inicio';
    }

    public function getHeading(): string|Htmlable|null
    {
        return '';
    }

    public function getWidgets(): array
    {
        return [
            PartnerOverviewWidget::class,
            PartnerInsightsWidget::class,
            PartnerActivityWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
