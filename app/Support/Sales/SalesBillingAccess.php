<?php

namespace App\Support\Sales;

use App\Models\User;

/**
 * Roles de supervisión que consultan ventas y estadísticas pero no operan caja ni alta directa.
 */
final class SalesBillingAccess
{
    /**
     * @var list<string>
     */
    public const ROLES_WITHOUT_BILLING = [
        'ADMINISTRADOR',
        'GERENCIA',
        'GERENTE',
        'COORDINADORES',
    ];

    public static function userCanBill(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return $user->canBillSales();
    }
}
