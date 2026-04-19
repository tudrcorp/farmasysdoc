<?php

namespace App\Filament\Resources\Concerns;

use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use App\Support\Filament\FarmaadminMenuAccessCatalog;
use Illuminate\Support\Facades\Auth;

/**
 * Limita listado y creación en recursos Farmaadmin para usuarios solo-entrega.
 */
trait RestrictsAccessForDeliveryUsers
{
    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    protected static function canAccessCurrentMenuItem(): bool
    {
        $user = request()->user() ?? Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        $menuKey = FarmaadminMenuAccessCatalog::resolveMenuKeyByRouteName(static::getRouteBaseName());

        if ($menuKey === null) {
            return true;
        }

        return $user->canAccessFarmaadminMenuKey($menuKey);
    }

    public static function canViewAny(): bool
    {
        if (FarmaadminDeliveryUserAccess::denies(static::class)) {
            return false;
        }

        if (! static::canAccessCurrentMenuItem()) {
            return false;
        }

        return static::getViewAnyAuthorizationResponse()->allowed();
    }

    public static function canCreate(): bool
    {
        if (FarmaadminDeliveryUserAccess::denies(static::class)) {
            return false;
        }

        if (! static::canAccessCurrentMenuItem()) {
            return false;
        }

        return static::getCreateAuthorizationResponse()->allowed();
    }
}
