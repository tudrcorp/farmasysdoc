<?php

namespace App\Filament\Resources\PettyCashFunds\Widgets;

use App\Enums\PettyCashFundStatus;
use App\Filament\Resources\PettyCashFunds\Concerns\SyncsWithPettyCashListBranchTab;
use App\Filament\Resources\PettyCashFunds\PettyCashFundResource;
use App\Services\Finance\PettyCashApprovedDisbursementTotals;
use App\Support\Finance\PettyCashMoney;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsListPettyCashOverview extends StatsOverviewWidget
{
    use SyncsWithPettyCashListBranchTab;

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

    protected ?string $heading = 'Egresos del mes';

    protected ?string $description = 'Fondos aprobados en el mes (desembolso al aprobar), más el disponible vigente.';

    public function mount(): void
    {
        $this->mountSyncsWithPettyCashListBranchTab();
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();

        $fundQuery = $this->applyPettyCashListBranchScope(PettyCashFundResource::getEloquentQuery());
        $disbursementTotals = app(PettyCashApprovedDisbursementTotals::class)->forFundScopeAndPeriod(
            $fundQuery,
            $from,
            $to,
        );

        $usd = (float) $disbursementTotals['usd'];
        $ves = (float) $disbursementTotals['ves'];
        $count = (int) $disbursementTotals['count'];

        $remainingUsd = (float) (clone $fundQuery)
            ->where('status', PettyCashFundStatus::Approved->value)
            ->sum('remaining_usd');
        $pending = (clone $fundQuery)
            ->where('status', PettyCashFundStatus::PendingApproval->value)
            ->count();

        return [
            Stat::make('Egresos USD (mes)', PettyCashMoney::formatUsd($usd))
                ->description($count === 1 ? '1 fondo aprobado (incl. agotadas)' : $count.' fondos aprobados (incl. agotadas)')
                ->descriptionColor('gray')
                ->color('gray')
                ->icon(Heroicon::CurrencyDollar)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-money']),
            Stat::make('Egresos VES (mes)', PettyCashMoney::formatVes($ves))
                ->description('Suma de montos transferidos al aprobar (2 decimales)')
                ->descriptionColor('gray')
                ->color('gray')
                ->icon(Heroicon::Banknotes)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-mail']),
            Stat::make('Disponible actual', PettyCashMoney::formatUsd($remainingUsd))
                ->description('Saldo de cajas chicas aprobadas')
                ->descriptionColor($remainingUsd > 0.009 ? 'success' : 'gray')
                ->color($remainingUsd > 0.009 ? 'success' : 'gray')
                ->icon(Heroicon::Wallet)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-money']),
            Stat::make('Por aprobar', (string) $pending)
                ->description($pending > 0 ? 'Solicitudes en espera' : 'Nada pendiente')
                ->descriptionColor($pending > 0 ? 'warning' : 'success')
                ->color($pending > 0 ? 'warning' : 'success')
                ->icon(Heroicon::Clock)
                ->extraAttributes(['class' => 'fi-marketing-stat-tone-pending']),
        ];
    }
}
