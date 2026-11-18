<?php

namespace App\Support\Inventory;

use App\Models\Branch;

/**
 * Sucursal principal de Farmadoc. El id cambia entre entornos, así que se resuelve por nombre.
 */
final class LasDeliciasBranch
{
    public static function resolve(): ?Branch
    {
        return Branch::query()
            ->where('is_active', true)
            ->where('name', 'like', '%Las Delicias%')
            ->orderBy('id')
            ->first();
    }
}
