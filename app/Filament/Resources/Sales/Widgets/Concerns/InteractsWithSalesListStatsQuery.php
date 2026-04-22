<?php

namespace App\Filament\Resources\Sales\Widgets\Concerns;

use App\Models\Sale;
use App\Support\Filament\BranchAuthScope;
use App\Support\Filament\SaleEffectiveDateScope;
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
        $rows = (clone $base)
            ->select('payment_method')
            ->selectRaw('SUM(COALESCE(payment_usd, 0)) as total_usd')
            ->selectRaw('SUM(COALESCE(payment_ves, 0)) as total_ves')
            ->groupBy('payment_method')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $key = strtolower(trim((string) ($row->payment_method ?? '')));
            if ($key === '') {
                $key = '__empty';
            }

            $map[$key] = [
                'usd' => (float) $row->total_usd,
                'ves' => (float) $row->total_ves,
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
