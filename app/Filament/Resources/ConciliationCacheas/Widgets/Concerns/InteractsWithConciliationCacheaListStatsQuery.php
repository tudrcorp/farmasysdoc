<?php

namespace App\Filament\Resources\ConciliationCacheas\Widgets\Concerns;

use App\Enums\ConciliationCacheaCollectionStatus;
use App\Models\ConciliationCachea;
use App\Support\Filament\BranchAuthScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Livewire\Attributes\Reactive;

trait InteractsWithConciliationCacheaListStatsQuery
{
    /**
     * @var array<string, mixed>|null
     */
    #[Reactive]
    public ?array $tableFilters = null;

    /**
     * @return Builder<ConciliationCachea>
     */
    protected function scopedConciliationCacheaQuery(): Builder
    {
        $query = ConciliationCachea::query();
        BranchAuthScope::apply($query);

        $filters = $this->tableFilters ?? [];
        $range = $filters['recorded_date_range'] ?? [];
        $range = is_array($range) ? $range : [];

        $from = filled($range['recorded_from'] ?? null) ? (string) $range['recorded_from'] : null;
        $until = filled($range['recorded_until'] ?? null) ? (string) $range['recorded_until'] : null;

        if ($from !== null) {
            $query->where('recorded_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($until !== null) {
            $query->where('recorded_at', '<=', Carbon::parse($until)->endOfDay());
        }

        $pending = self::normalizeTernaryFilterValue($filters['has_pending_remainder'] ?? null);
        if ($pending === true) {
            $query->where('remainder', '>', 0);
        } elseif ($pending === false) {
            $query->where('remainder', '<=', 0);
        }

        $branchIds = self::normalizeBranchFilterIds($filters['branch_id'] ?? null);
        if ($branchIds !== []) {
            $query->whereIn('branch_id', $branchIds);
        }

        ConciliationCacheaCollectionStatus::applyTableFilterScope(
            $query,
            self::normalizeSelectFilterValue($filters['collection_status_visibility'] ?? null) ?? 'pending',
        );

        return $query;
    }

    private static function normalizeSelectFilterValue(mixed $state): ?string
    {
        if (is_array($state) && array_key_exists('value', $state)) {
            $state = $state['value'];
        }

        if (! is_string($state) || $state === '') {
            return null;
        }

        return $state;
    }

    /**
     * @return list<int>
     */
    private static function normalizeBranchFilterIds(mixed $branchFilter): array
    {
        if (blank($branchFilter)) {
            return [];
        }

        if (is_array($branchFilter)) {
            if (array_key_exists('values', $branchFilter)) {
                $branchFilter = $branchFilter['values'];
            } elseif (array_key_exists('value', $branchFilter)) {
                $branchFilter = $branchFilter['value'];
            }
        }

        return collect(Arr::wrap($branchFilter))
            ->flatten()
            ->filter(fn (mixed $id): bool => filled($id) && is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private static function normalizeTernaryFilterValue(mixed $state): ?bool
    {
        if (is_array($state) && array_key_exists('value', $state)) {
            $state = $state['value'];
        }

        if ($state === null || $state === '') {
            return null;
        }

        return filter_var($state, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
