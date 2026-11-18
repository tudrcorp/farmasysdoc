<?php

namespace App\Filament\Resources\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Lectura para quien tenga la clave de menú del recurso; crear, editar y eliminar solo ADMINISTRADOR.
 */
trait ViewableByMenuKeyManagedByAdministrators
{
    use ChecksConfigurationAccess;

    public static function canCreate(): bool
    {
        return static::currentUserIsAdministrator();
    }

    public static function canEdit(Model $record): bool
    {
        return static::currentUserIsAdministrator();
    }

    public static function canDelete(Model $record): bool
    {
        return static::currentUserIsAdministrator();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static function currentUserIsAdministrator(): bool
    {
        $user = request()->user() ?? Auth::user();

        return static::canViewAny() && $user instanceof User && $user->isAdministrator();
    }
}
