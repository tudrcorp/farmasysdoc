<?php

namespace App\Support\Cash;

use App\Models\PhysicalCashBox;
use App\Models\User;
use App\Support\Sales\SalesBillingAccess;

/**
 * Reglas de acceso a la caja registradora según apertura de caja física.
 */
final class PhysicalCashBoxBillingGate
{
    public static function userMayUseCashRegister(?User $user): bool
    {
        if (! SalesBillingAccess::userCanBill($user)) {
            return false;
        }

        if (! $user instanceof User || ! $user->isCashier()) {
            return true;
        }

        return PhysicalCashBox::query()
            ->where('user_id', $user->id)
            ->where('is_open', true)
            ->exists();
    }
}
