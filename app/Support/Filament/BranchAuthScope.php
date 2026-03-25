<?php

namespace App\Support\Filament;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Alcance por sucursal en paneles Filament: ADMINISTRADOR ve todo; el resto solo su `branch_id`.
 */
final class BranchAuthScope
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query): Builder
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $query;
        }

        if ($user->isAdministrator()) {
            return $query;
        }

        if ($user->branch_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('branch_id', $user->branch_id);
    }
}
