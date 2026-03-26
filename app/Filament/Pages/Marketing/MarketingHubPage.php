<?php

namespace App\Filament\Pages\Marketing;

use App\Filament\Widgets\Marketing\MarketingBranchInsightsWidget;
use App\Filament\Widgets\Marketing\MarketingBranchRevenueChartWidget;
use App\Filament\Widgets\Marketing\MarketingStatsOverviewWidget;
use App\Filament\Widgets\Marketing\MarketingTopProductsChartWidget;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class MarketingHubPage extends Dashboard
{
    protected static string $routePath = 'marketing';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = -10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Megaphone;

    public static function getNavigationLabel(): string
    {
        return 'Panel de marketing';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Marketing';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Métricas, campañas y comunicación con tus clientes.';
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['fi-marketing-hub-page'];
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessMarketingModule();
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canAccessMarketingModule();
    }

    /**
     * @return array<class-string<Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            MarketingStatsOverviewWidget::class,
            MarketingBranchRevenueChartWidget::class,
            MarketingTopProductsChartWidget::class,
            MarketingBranchInsightsWidget::class,
        ];
    }

    /**
     * @return int | array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }
}
