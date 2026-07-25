<?php

namespace App\Filament\Resources\PurchaseBooks\Widgets;

use App\Filament\Resources\PurchaseBooks\Widgets\Concerns\InteractsWithPurchaseBookListStatsQuery;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsPurchaseBookOverview extends StatsOverviewWidget
{
    use InteractsWithPurchaseBookListStatsQuery;

    protected static bool $isDiscovered = false;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.stats-purchase-book-overview';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /**
     * @var int|array<string, ?int>|null
     */
    protected int|array|null $columns = ['@sm' => 1, '@md' => 2, '@lg' => 4];

    protected ?string $heading = 'Resumen del mes en curso';

    protected ?string $description = 'Indicadores fiscales solo del mes actual. Los filtros de proveedor o fechas acotan dentro de este mes; el periodo de la tabla no altera este resumen.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $base = $this->scopedPurchaseBookQuery();
        $periodLabel = $this->statsPeriodLabel();
        $periodCode = $this->statsPeriodCode();
        $scopeHint = $this->statsScopeHint();

        $periodCount = (clone $base)->count();
        $supplierCount = (int) (clone $base)
            ->selectRaw('count(distinct supplier_rif) as aggregate')
            ->value('aggregate');
        $periodBase = (float) (clone $base)->sum('taxable_base_ves');
        $periodTax = (float) (clone $base)->sum('tax_caused_ves');
        $periodRetained = (float) (clone $base)->sum('tax_retained_ves');
        $retentionRatio = $periodTax > 0
            ? round(($periodRetained / $periodTax) * 100, 1)
            : 0.0;

        return [
            Stat::make('Registros del mes', (string) $periodCount)
                ->description($periodLabel.' · mes en curso')
                ->descriptionIcon(Heroicon::CalendarDays)
                ->descriptionColor('gray')
                ->color('primary')
                ->icon(Heroicon::BookOpen)
                ->extraAttributes([
                    'class' => 'farmadoc-pb-stat-tone-ops',
                    'title' => $scopeHint,
                ]),
            Stat::make('Proveedores del mes', (string) $supplierCount)
                ->description('RIF distintos con operaciones')
                ->descriptionIcon(Heroicon::Truck)
                ->descriptionColor('gray')
                ->color('info')
                ->icon(Heroicon::BuildingStorefront)
                ->extraAttributes([
                    'class' => 'farmadoc-pb-stat-tone-suppliers',
                    'title' => $scopeHint,
                ]),
            Stat::make('Base imponible', self::formatBs($periodBase))
                ->description('Periodo '.$periodCode)
                ->descriptionIcon(Heroicon::Calculator)
                ->descriptionColor('gray')
                ->color('gray')
                ->icon(Heroicon::Scale)
                ->extraAttributes([
                    'class' => 'farmadoc-pb-stat-tone-base',
                    'title' => $scopeHint,
                ]),
            Stat::make('Impuesto retenido', self::formatBs($periodRetained))
                ->description('IVA causado: '.self::formatBs($periodTax).' · '.$retentionRatio.'%')
                ->descriptionIcon(Heroicon::DocumentText)
                ->descriptionColor('warning')
                ->color('warning')
                ->icon(Heroicon::Banknotes)
                ->extraAttributes([
                    'class' => 'farmadoc-pb-stat-tone-retained',
                    'title' => 'Retenido = IVA causado × % SENIAT del proveedor · '.$scopeHint,
                ]),
        ];
    }

    private static function formatBs(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' Bs';
    }
}
