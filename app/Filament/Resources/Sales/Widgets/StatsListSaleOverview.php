<?php

namespace App\Filament\Resources\Sales\Widgets;

use App\Filament\Resources\Sales\Widgets\Concerns\InteractsWithSalesListStatsQuery;
use App\Support\Sales\SaleCollectedMoneyAggregator;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class StatsListSaleOverview extends StatsOverviewWidget
{
    use InteractsWithSalesListStatsQuery;

    protected static bool $isDiscovered = false;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.stats-list-sale-overview';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '10s';

    /**
     * @var int|array<string, ?int>|null
     */
    protected int|array|null $columns = ['@sm' => 1, '@md' => 2, '@lg' => 3];

    protected ?string $heading = 'Resumen del período';

    protected ?string $description = 'Alineado al rango «Fecha de venta» de los filtros de la tabla. Incluye las ventas del criterio (rol cajero: solo las registradas por usted), sin filtrar por método de pago.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $sales = $this->scopedSaleQuery()
            ->with(['conciliationCachea', 'posTerminal'])
            ->get();

        $documentTotalUsd = round((float) $sales->sum('total'), 2);
        $collected = app(SaleCollectedMoneyAggregator::class)->collectedTotals($sales);
        $totalUsdCollected = $collected['usd'];
        $totalVesCollected = $collected['ves'];

        return [
            Stat::make('Total ventas (USD documento)', Number::currency($documentTotalUsd, 'USD', 'en', 2))
                ->description('Suma del total de cada venta en el período')
                ->descriptionColor('gray')
                ->color('gray')
                ->icon(Heroicon::ChartBarSquare)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-money']),
            Stat::make('Cobros USD', Number::currency($totalUsdCollected, 'USD', 'en', 2))
                ->description('Solo cobros en dólares. No incluye bolívares convertidos.')
                ->descriptionColor('gray')
                ->color('gray')
                ->icon(Heroicon::CurrencyDollar)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-money']),
            Stat::make('Cobros VES', self::formatBs($totalVesCollected))
                ->description('Solo cobros en bolívares. No incluye dólares convertidos.')
                ->descriptionColor('gray')
                ->color('gray')
                ->icon(Heroicon::Banknotes)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-mail']),
        ];
    }

    private static function formatBs(float $amount): string
    {
        return 'Bs. '.number_format(round($amount, 2), 2, ',', '.');
    }
}
