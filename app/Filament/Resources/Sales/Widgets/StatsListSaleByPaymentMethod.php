<?php

namespace App\Filament\Resources\Sales\Widgets;

use App\Filament\Resources\Sales\Widgets\Concerns\InteractsWithSalesListStatsQuery;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class StatsListSaleByPaymentMethod extends StatsOverviewWidget
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
    protected int|array|null $columns = ['@sm' => 1, '@md' => 2, '@lg' => 3, '@xl' => 3];

    protected ?string $heading = 'Totales por forma de pago';

    protected ?string $description = 'Cobro registrado en cada venta del período (USD y/o bolívares), según el método elegido en caja.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $base = $this->scopedSaleQuery();
        $byMethod = $this->aggregatePaymentTotalsByMethod($base);

        $stats = [];
        foreach (self::paymentMethodRows() as $row) {
            $key = $row['key'];
            $totals = $byMethod[$key] ?? ['usd' => 0.0, 'ves' => 0.0];
            $stats[] = self::makePaymentMethodStat(
                $row['label'],
                $totals['usd'],
                $totals['ves'],
                $row['icon'],
            );
        }

        return $stats;
    }

    /**
     * @return list<array{key: string, label: string, icon: Heroicon}>
     */
    private static function paymentMethodRows(): array
    {
        return [
            ['key' => 'efectivo_usd', 'label' => 'Efectivo USD', 'icon' => Heroicon::Banknotes],
            ['key' => 'efectivo_ves', 'label' => 'Efectivo VES', 'icon' => Heroicon::Banknotes],
            ['key' => 'transfer_ves', 'label' => 'Transferencia VES', 'icon' => Heroicon::BuildingLibrary],
            ['key' => 'zelle', 'label' => 'Zelle', 'icon' => Heroicon::PaperAirplane],
            ['key' => 'pago_movil', 'label' => 'Pago Movil', 'icon' => Heroicon::DevicePhoneMobile],
            ['key' => 'mixed', 'label' => 'Pago Multiple', 'icon' => Heroicon::ArrowsRightLeft],
        ];
    }

    private static function makePaymentMethodStat(string $label, float $usd, float $ves, Heroicon $icon): Stat
    {
        $hasUsd = abs($usd) > 0.00001;
        $hasVes = abs($ves) > 0.00001;

        if ($hasUsd && $hasVes) {
            return Stat::make($label, Number::currency($usd, 'USD', 'en', 2))
                ->description(self::formatBs($ves).' en bolívares')
                ->descriptionColor('gray')
                ->color('gray')
                ->icon($icon)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-phone']);
        }

        if ($hasUsd) {
            return Stat::make($label, Number::currency($usd, 'USD', 'en', 2))
                ->description('Cobro en USD')
                ->descriptionColor('gray')
                ->color('gray')
                ->icon($icon)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-send']);
        }

        if ($hasVes) {
            return Stat::make($label, self::formatBs($ves))
                ->description('Cobro en bolívares')
                ->descriptionColor('gray')
                ->color('gray')
                ->icon($icon)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-phone']);
        }

        return Stat::make($label, '—')
            ->description('Sin movimientos en el período')
            ->descriptionColor('gray')
            ->color('gray')
            ->icon($icon)
            ->extraAttributes(['class' => 'fi-marketing-stat-tone-mail']);
    }

    private static function formatBs(float $amount): string
    {
        return 'Bs. '.number_format(round($amount, 2), 2, ',', '.');
    }
}
