<?php

namespace App\Filament\Resources\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Recursos del grupo de navegación «Configuración»: solo rol ADMINISTRADOR.
 */
trait ChecksConfigurationAccess
{
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdministrator();
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
