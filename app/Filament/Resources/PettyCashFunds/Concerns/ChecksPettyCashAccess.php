<?php

namespace App\Filament\Resources\PettyCashFunds\Concerns;

use App\Enums\PettyCashFundStatus;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use App\Support\Filament\FarmaadminMenuAccessCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait ChecksPettyCashAccess
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

        $user = static::currentUser();
        if (! $user instanceof User || ! $user->canManagePettyCash()) {
            return false;
        }

        $menuKey = FarmaadminMenuAccessCatalog::resolveMenuKeyByRouteName(static::getRouteBaseName());
        if ($menuKey === null) {
            return true;
        }

        return $user->canAccessFarmaadminMenuKey($menuKey);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        $user = static::currentUser();
        if (! $user instanceof User || ! $record instanceof PettyCashFund) {
            return false;
        }

        if ($record->status !== PettyCashFundStatus::PendingApproval) {
            return false;
        }

        return $user->isAdministrator() || $record->requested_by === $user->id;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function canDeleteAny(): bool
    {
        $user = static::currentUser();

        return $user instanceof User && $user->isAdministrator();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function currentUser(): ?User
    {
        $user = request()->user() ?? Auth::user();

        return $user instanceof User ? $user : null;
    }
}
