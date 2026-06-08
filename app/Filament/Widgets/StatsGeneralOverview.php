<?php

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Filament\Widgets\Concerns\InteractsWithDashboardBranchFilter;
use App\Models\Sale;
use App\Support\Filament\DashboardBranchFilter;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class StatsGeneralOverview extends StatsOverviewWidget
{
    use InteractsWithDashboardBranchFilter;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.stats-general-overview';

    protected static ?int $sort = -2;

    /**
     * @var int|array<string, ?int>|null
     */
    protected int|array|null $columns = ['@sm' => 1, '@md' => 2];

    protected ?string $heading = 'Cobros por moneda';

    protected ?string $description = 'Ventas completadas · alcance por sucursal (rol cajero: solo ventas propias)';

    public function getDescription(): ?string
    {
        return 'Ventas completadas · alcance por sucursal (rol cajero: solo ventas propias).'
            .$this->dashboardBranchFilterSuffix();
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $query = Sale::query()
            ->where('status', SaleStatus::Completed);

        DashboardBranchFilter::applyToSalesQuery($query);

        $totalUsd = (float) (clone $query)->sum('payment_usd');
        $totalVes = (float) (clone $query)->sum('payment_ves');

        return [
            Stat::make('Ventas en USD', Number::currency($totalUsd, 'USD', 'en', 2))
                ->description('Cobro en USD')
                ->descriptionColor('success')
                ->color('success')
                ->icon(Heroicon::CurrencyDollar)
                ->extraAttributes([
                    'class' => 'fi-farmaadmin-ios-sales-stat fi-farmaadmin-ios-sales-stat--usd',
                ]),
            Stat::make('Ventas en Bs.', 'Bs. '.number_format($totalVes, 2, ',', '.'))
                ->description('Cobro en bolívares')
                ->descriptionColor('info')
                ->color('info')
                ->icon(Heroicon::Banknotes)
                ->extraAttributes([
                    'class' => 'fi-farmaadmin-ios-sales-stat fi-farmaadmin-ios-sales-stat--ves',
                ]),
        ];
    }
}
