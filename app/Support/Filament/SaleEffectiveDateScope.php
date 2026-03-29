<?php

namespace App\Support\Filament;

use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtro por fecha efectiva de venta: {@see Sale::$sold_at} o {@see Sale::$created_at}.
 */
final class SaleEffectiveDateScope
{
    /**
     * @param  Builder<Sale>  $query
     */
    public static function apply(Builder $query, ?string $from, ?string $until): void
    {
        $fromFilled = filled($from);
        $untilFilled = filled($until);

        if (! $fromFilled && ! $untilFilled) {
            $query->whereRaw('COALESCE(sold_at, created_at) >= ?', [now()->startOfDay()])
                ->whereRaw('COALESCE(sold_at, created_at) <= ?', [now()->endOfDay()]);

            return;
        }

        if ($fromFilled) {
            $query->whereRaw('COALESCE(sold_at, created_at) >= ?', [Carbon::parse((string) $from)->startOfDay()]);
        }

        if ($untilFilled) {
            $query->whereRaw('COALESCE(sold_at, created_at) <= ?', [Carbon::parse((string) $until)->endOfDay()]);
        }
    }
}
