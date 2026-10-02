<?php

namespace App\Services\Fiscal;

use App\Models\FiscalPrinter;
use App\Models\PhysicalCashBox;
use App\Models\User;

/**
 * Decide en qué máquina fiscal se imprime una venta: solo la asignada explícitamente a la caja física
 * del cajero. Sin asignación no hay impresión fiscal (evita imprimir en la máquina de otra caja,
 * p. ej. mientras otras cajas de la sucursal siguen con otro sistema).
 */
final class FiscalPrinterResolver
{
    public function forCashier(?User $cashier, int $branchId): ?FiscalPrinter
    {
        if (! $cashier instanceof User) {
            return null;
        }

        return FiscalPrinter::query()
            ->where('is_active', true)
            ->where('branch_id', $branchId)
            ->whereIn('physical_cash_box_id', PhysicalCashBox::query()
                ->select('id')
                ->where('user_id', $cashier->id))
            ->first();
    }
}
