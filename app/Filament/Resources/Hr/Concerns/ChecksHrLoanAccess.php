<?php

namespace App\Filament\Resources\Hr\Concerns;

use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait ChecksHrLoanAccess
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

        return $user instanceof User && $user->canManageHrLoans();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        $user = request()->user() ?? Auth::user();

        return $user instanceof User && $user->isAdministrator();
    }

    public static function canDelete(Model $record): bool
    {
        $user = request()->user() ?? Auth::user();

        return $user instanceof User && $user->isAdministrator();
    }

    public static function canDeleteAny(): bool
    {
        $user = request()->user() ?? Auth::user();

        return $user instanceof User && $user->isAdministrator();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }
}
