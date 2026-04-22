<?php

namespace App\Filament\Resources\Sales\Widgets;

use App\Filament\Resources\Sales\Widgets\Concerns\InteractsWithSalesListStatsQuery;
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
        $base = $this->scopedSaleQuery();

        $documentTotalUsd = (float) (clone $base)->sum('total');
        $totalUsdCollected = (float) (clone $base)->sum('payment_usd');
        $totalVesCollected = (float) (clone $base)->sum('payment_ves');

        return [
            Stat::make('Total ventas (USD documento)', Number::currency($documentTotalUsd, 'USD', 'en', 2))
                ->description('Suma del total de cada venta en el período')
                ->descriptionColor('gray')
                ->color('gray')
                ->icon(Heroicon::ChartBarSquare)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-money']),
            Stat::make('Cobros USD (todas)', Number::currency($totalUsdCollected, 'USD', 'en', 2))
                ->description('Suma de payment_usd en el período')
                ->descriptionColor('gray')
                ->color('gray')
                ->icon(Heroicon::CurrencyDollar)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-money']),
            Stat::make('Cobros VES (todas)', self::formatBs($totalVesCollected))
                ->description('Suma de payment_ves en el período')
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
