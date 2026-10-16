<?php

namespace App\Support\Fiscal;

use App\Models\Branch;
use App\Models\User;

/**
 * Permisos del Libro de Ventas: ver/descargar (menú «sales_book»), cerrar (permiso «sales_book_close»)
 * y reabrir (solo administradores). Siempre limitado a las sucursales del usuario.
 */
final class SalesBookAccess
{
    public const MENU_KEY = 'sales_book';

    public const CLOSE_PERMISSION = 'sales_book_close';

    public static function canView(?User $user): bool
    {
        return $user instanceof User
            && ($user->isAdministrator() || $user->canAccessFarmaadminMenuKey(self::MENU_KEY));
    }

    public static function canClose(?User $user): bool
    {
        return $user instanceof User
            && ($user->isAdministrator() || $user->canAccessFarmaadminMenuKey(self::CLOSE_PERMISSION));
    }

    public static function canReopen(?User $user): bool
    {
        return $user instanceof User && $user->isAdministrator();
    }

    public static function canAccessBranch(?User $user, int $branchId): bool
    {
        if (! $user instanceof User || $branchId <= 0) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        return in_array($branchId, $user->restrictedBranchIdsForQueries(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function branchOptions(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $query = Branch::query()->where('is_active', true)->orderBy('name');

        if (! $user->isAdministrator()) {
            $query->whereIn('id', $user->restrictedBranchIdsForQueries() ?: [0]);
        }

        return $query->pluck('name', 'id')->all();
    }

    public static function actorLabel(?User $user): string
    {
        if (! $user instanceof User) {
            return 'sistema';
        }

        return filled($user->email) ? (string) $user->email : (string) ($user->name ?? 'usuario');
    }
}
