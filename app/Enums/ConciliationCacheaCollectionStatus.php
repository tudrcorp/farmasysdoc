<?php

namespace App\Enums;

use Illuminate\Database\Eloquent\Builder;

enum ConciliationCacheaCollectionStatus: string
{
    case PendingCollection = 'monto_por_cobrar';
    case AmountReceived = 'monto_recibido';

    public function label(): string
    {
        return match ($this) {
            self::PendingCollection => 'Monto por cobrar',
            self::AmountReceived => 'Monto recibido',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::PendingCollection => 'warning',
            self::AmountReceived => 'success',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function filterOptions(): array
    {
        return [
            'pending' => 'Monto por cobrar',
            'received' => 'Monto recibido',
            'all' => 'Todos',
        ];
    }

    public static function applyTableFilterScope(Builder $query, ?string $filterKey): void
    {
        match ($filterKey) {
            'received' => $query->where('collection_status', self::AmountReceived),
            'all' => null,
            default => $query->where('collection_status', self::PendingCollection),
        };
    }
}
