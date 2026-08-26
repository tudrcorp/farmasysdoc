<?php

namespace App\Filament\Resources\Sales\Widgets\Concerns;

use App\Models\Sale;
use App\Support\Filament\BranchAuthScope;
use App\Support\Filament\SaleEffectiveDateScope;
use App\Support\Sales\SaleCollectedMoneyAggregator;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;

trait InteractsWithSalesListStatsQuery
{
    /**
     * Inyectado desde la página de listado vía {@see ExposesTableToWidgets}.
     *
     * @var array<string, mixed>|null
     */
    #[Reactive]
    public ?array $tableFilters = null;

    /**
     * @return array<string, array{usd: float, ves: float}>
     */
    protected function aggregatePaymentTotalsByMethod(Builder $base): array
    {
        $sales = (clone $base)
            ->with(['conciliationCachea', 'posTerminal'])
            ->get();

        $aggregated = app(SaleCollectedMoneyAggregator::class)->collectedByChannel($sales);

        $map = [];
        foreach ($aggregated as $key => $row) {
            $map[$key] = [
                'usd' => $row['usd'],
                'ves' => $row['ves'],
            ];
        }

        return $map;
    }

    /**
     * @return Builder<Sale>
     */
    protected function scopedSaleQuery(): Builder
    {
        $query = Sale::query();
        BranchAuthScope::applyToSalesQuery($query);

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
