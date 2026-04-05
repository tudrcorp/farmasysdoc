<?php

namespace App\Filament\Resources\Concerns;

use App\Support\Filament\FarmaadminDeliveryUserAccess;

/**
 * Limita listado y creación en recursos Farmaadmin para usuarios solo-entrega.
 */
trait RestrictsAccessForDeliveryUsers
{
    public static function canViewAny(): bool
    {
        if (FarmaadminDeliveryUserAccess::denies(static::class)) {
            return false;
        }

        return static::getViewAnyAuthorizationResponse()->allowed();
    }

    public static function canCreate(): bool
    {
        if (FarmaadminDeliveryUserAccess::denies(static::class)) {
            return false;
        }

        return static::getCreateAuthorizationResponse()->allowed();
    }
}
