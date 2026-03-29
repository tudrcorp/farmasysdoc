<?php

namespace App\Filament\Resources\Sales\Widgets;

use App\Models\Sale;
use App\Support\Filament\BranchAuthScope;
use App\Support\Filament\SaleEffectiveDateScope;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Livewire\Attributes\Reactive;

class StatsListSaleOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.stats-list-sale-overview';

    protected int|string|array $columnSpan = 'full';

    /**
     * @var int|array<string, ?int>|null
     */
    protected int|array|null $columns = ['@sm' => 1, '@md' => 2, '@lg' => 4];

    protected ?string $heading = 'Totales en USD por forma de pago';

    protected ?string $description = 'Alineado al rango «Fecha de venta» de los filtros de la tabla.';

    /**
     * Inyectado desde la página de listado vía {@see ExposesTableToWidgets}.
     *
     * @var array<string, mixed>|null
     */
    #[Reactive]
    public ?array $tableFilters = null;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $totals = $this->getPaymentUsdTotals();

        return [
            Stat::make('Total general (USD)', Number::currency($totals['all'], 'USD', 'en', 2))
                ->description('Suma de cobros en USD del período')
                ->descriptionColor('gray')
                ->color('gray')
                ->icon(Heroicon::CurrencyDollar)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-money']),
            Stat::make('Efectivo USD', Number::currency($totals['efectivo_usd'], 'USD', 'en', 2))
                ->description('Ventas en efectivo (USD)')
                ->descriptionColor('gray')
                ->color('gray')
                ->icon(Heroicon::Banknotes)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-mail']),
            Stat::make('Zelle', Number::currency($totals['zelle'], 'USD', 'en', 2))
                ->description('Cobros vía Zelle')
                ->descriptionColor('gray')
                ->color('gray')
                ->icon(Heroicon::PaperAirplane)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-send']),
            Stat::make('Pago móvil', Number::currency($totals['pago_movil'], 'USD', 'en', 2))
                ->description('Cobros vía pago móvil (USD)')
                ->descriptionColor('gray')
                ->color('gray')
                ->icon(Heroicon::DevicePhoneMobile)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-phone']),
        ];
    }

    /**
     * @return array{all: float, efectivo_usd: float, zelle: float, pago_movil: float}
     */
    protected function getPaymentUsdTotals(): array
    {
        $base = $this->scopedSaleQuery();

        return [
            'all' => (float) (clone $base)->sum('payment_usd'),
            'efectivo_usd' => (float) (clone $base)->where('payment_method', 'efectivo_usd')->sum('payment_usd'),
            'zelle' => (float) (clone $base)->where('payment_method', 'zelle')->sum('payment_usd'),
            'pago_movil' => (float) (clone $base)->where('payment_method', 'pago_movil')->sum('payment_usd'),
        ];
    }

    /**
     * @return Builder<Sale>
     */
    protected function scopedSaleQuery(): Builder
    {
        $query = Sale::query();
        BranchAuthScope::apply($query);

        $filters = $this->tableFilters ?? [];
        $range = $filters['sold_date_range'] ?? [];
        $range = is_array($range) ? $range : [];

        SaleEffectiveDateScope::apply(
            $query,
            filled($range['sold_from'] ?? null) ? (string) $range['sold_from'] : null,
            filled($range['sold_until'] ?? null) ? (string) $range['sold_until'] : null,
        );

        return $query;
    }
}
