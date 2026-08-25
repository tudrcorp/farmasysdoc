<?php

namespace App\Support\Cash;

use App\Models\PhysicalCashBox;
use App\Models\User;
use App\Services\Branches\BranchDailyOperationService;
use App\Support\Sales\SalesBillingAccess;

/**
 * Reglas de acceso a la caja registradora según apertura de sucursal y caja física.
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

        if (! self::branchDailyOperationIsOpen($user)) {
            return false;
        }

        return PhysicalCashBox::query()
            ->where('user_id', $user->id)
            ->where('is_open', true)
            ->exists();
    }

    public static function userMayOpenPhysicalCashBox(?User $user): bool
    {
        return $user instanceof User
            && $user->isCashier()
            && self::branchDailyOperationIsOpen($user);
    }

    public static function cashRegisterUnavailableMessage(?User $user): string
    {
        if (! $user instanceof User || ! SalesBillingAccess::userCanBill($user)) {
            return 'Su rol solo puede consultar el listado y las estadísticas de ventas, no registrar ventas en caja.';
        }

        if ($user->isCashier() && ! self::branchDailyOperationIsOpen($user)) {
            return 'La sucursal aún no ha sido aperturada. Espere a que gerencia o administración abra la gestión del día.';
        }

        return 'Debe abrir la caja física antes de usar la caja registradora.';
    }

    public static function branchDailyOperationIsOpen(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return app(BranchDailyOperationService::class)->isOpenForUser($user);
    }
}
