<?php

namespace App\Filament\Resources\Concerns;

use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use App\Support\Filament\FarmaadminMenuAccessCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Recursos restringidos a rol ADMINISTRADOR (p. ej. configuración o aliados comerciales).
 */
trait ChecksConfigurationAccess
{
    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        if (FarmaadminDeliveryUserAccess::denies(static::class)) {
            return false;
        }

        $user = request()->user() ?? Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        $menuKey = FarmaadminMenuAccessCatalog::resolveMenuKeyByRouteName(static::getRouteBaseName());

        if ($menuKey === null) {
            return $user->isAdministrator();
        }

        return $user->canAccessFarmaadminMenuKey($menuKey);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }
}
