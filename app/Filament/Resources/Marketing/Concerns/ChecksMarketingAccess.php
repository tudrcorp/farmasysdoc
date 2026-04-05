<?php

namespace App\Filament\Resources\Marketing\Concerns;

use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use Illuminate\Database\Eloquent\Model;

trait ChecksMarketingAccess
{
    public static function canViewAny(): bool
    {
        if (FarmaadminDeliveryUserAccess::denies(static::class)) {
            return false;
        }

        $user = auth()->user();

        return $user instanceof User && $user->canAccessMarketingModule();
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
