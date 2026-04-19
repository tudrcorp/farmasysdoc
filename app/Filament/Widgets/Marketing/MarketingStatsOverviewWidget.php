<?php

namespace App\Filament\Widgets\Marketing;

use App\Models\User;
use App\Services\Marketing\MarketingAnalyticsService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MarketingStatsOverviewWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var int|array<string, ?int>|null
     */
    protected int|array|null $columns = ['@sm' => 2, '@lg' => 3, '@3xl' => 4];

    protected ?string $heading = 'Resumen';

    protected ?string $description = 'Indicadores con estilo vidrio iOS y degradados suaves por métrica.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = app(MarketingAnalyticsService::class)->dashboardSummary();

        return [
            Stat::make('Clientes registrados', number_format($summary['total_clients']))
                ->description('Total en base de datos')
                ->descriptionColor('gray')
                ->color('gray')
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-clients']),
            Stat::make('Clientes con correo', number_format($summary['clients_with_email']))
                ->description('Contactos con email para campañas')
                ->descriptionColor('gray')
                ->color('gray')
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-mail']),
            Stat::make('Clientes con teléfono', number_format($summary['clients_with_phone']))
                ->description('SMS y mensajería')
                ->descriptionColor('gray')
                ->color('gray')
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-phone']),
            Stat::make('Campañas activas', (string) $summary['active_campaigns'])
                ->description('Campañas en ejecución')
                ->descriptionColor('gray')
                ->color('gray')
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-campaign']),
            Stat::make('Envíos (30 días)', (string) $summary['broadcasts_completed_30d'])
                ->description('Difusiones completadas')
                ->descriptionColor('gray')
                ->color('gray')
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-send']),
            Stat::make('Ventas completadas', number_format($summary['total_completed_sales']))
                ->description('Ventas cerradas en el sistema')
                ->descriptionColor('gray')
                ->color('gray')
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-sales']),
            Stat::make('Ingresos totales', $summary['revenue_total'])
                ->description('Suma de ventas completadas')
                ->descriptionColor('gray')
                ->color('gray')
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-money']),
        ];
    }

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessFarmaadminMenuKey('marketing_hub');
    }
}
