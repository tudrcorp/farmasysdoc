<?php

namespace App\Filament\Resources\ConciliationCacheas\Widgets;

use App\Filament\Resources\ConciliationCacheas\Widgets\Concerns\InteractsWithConciliationCacheaListStatsQuery;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class StatsListConciliationCacheaOverview extends StatsOverviewWidget
{
    use InteractsWithConciliationCacheaListStatsQuery;

    protected static bool $isDiscovered = false;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.stats-list-conciliation-cachea-overview';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '15s';

    /**
     * @var int|array<string, ?int>|null
     */
    protected int|array|null $columns = ['@sm' => 1, '@md' => 2, '@lg' => 4];

    protected ?string $heading = 'Resumen Cachea';

    protected ?string $description = 'Totales según los filtros activos de la tabla. El resto pendiente es lo que la farmacia debe recibir de Cachea.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $base = $this->scopedConciliationCacheaQuery();

        $count = (clone $base)->count();
        $saleTotal = (float) (clone $base)->sum('sale_total');
        $cacheaPaid = (float) (clone $base)->sum('cachea_paid_amount');
        $remainder = (float) (clone $base)->sum('remainder');
        $pendingCount = (clone $base)->where('remainder', '>', 0)->count();

        return [
            Stat::make('Operaciones', (string) $count)
                ->description($pendingCount > 0
                    ? "{$pendingCount} con resto pendiente"
                    : 'Sin restos pendientes')
                ->descriptionIcon($pendingCount > 0 ? Heroicon::Clock : Heroicon::CheckCircle)
                ->descriptionColor($pendingCount > 0 ? 'warning' : 'success')
                ->color('info')
                ->icon(Heroicon::QueueList)
                ->extraAttributes(['class' => 'farmadoc-cachea-stat-tone-ops']),
            Stat::make('Total ventas', Number::currency($saleTotal, 'USD', 'en', 2))
                ->description('Documento de venta en USD')
                ->descriptionIcon(Heroicon::DocumentText)
                ->descriptionColor('gray')
                ->color('gray')
                ->icon(Heroicon::DocumentCurrencyDollar)
                ->extraAttributes(['class' => 'farmadoc-cachea-stat-tone-sales']),
            Stat::make('Pagado con Cachea', Number::currency($cacheaPaid, 'USD', 'en', 2))
                ->description('Abonado por el cliente vía Cachea')
                ->descriptionIcon(Heroicon::CheckBadge)
                ->descriptionColor('success')
                ->color('success')
                ->icon(Heroicon::CheckBadge)
                ->extraAttributes(['class' => 'farmadoc-cachea-stat-tone-paid']),
            Stat::make('Resto pendiente', Number::currency($remainder, 'USD', 'en', 2))
                ->description($remainder > 0.00001
                    ? 'Por cobrar a Cachea'
                    : 'Nada pendiente en el criterio')
                ->descriptionIcon($remainder > 0.00001 ? Heroicon::ExclamationTriangle : Heroicon::CheckCircle)
                ->descriptionColor($remainder > 0.00001 ? 'warning' : 'success')
                ->color($remainder > 0.00001 ? 'warning' : 'success')
                ->icon(Heroicon::Banknotes)
                ->extraAttributes(['class' => 'farmadoc-cachea-stat-tone-remainder']),
        ];
    }
}
