<?php

namespace App\Support\Products;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class ProductDirectPriceAccess
{
    public static function canView(?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->canSeeProductDirectPrice();
    }

    public static function canEdit(?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->canEditProductDirectPrice();
    }
}
