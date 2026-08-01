<?php

namespace App\Services\Inventory;

use App\Enums\InventoryAuditLineStatus;
use App\Enums\InventoryAuditStatus;
use App\Models\Inventory;
use App\Models\InventoryAuditLine;

/**
 * Mantiene alineada la existencia de sistema en líneas pendientes de una auditoría abierta
 * cuando el stock cambia por operaciones de caja (venta / anulación).
 */
final class InventoryAuditOpenLineSyncService
{
    /**
     * Si el inventario pertenece a una línea pendiente de una auditoría abierta en la sucursal,
     * actualiza system_quantity al stock actual. No toca líneas ya procesadas.
     */
    public function syncPendingLineForInventory(Inventory $inventory): bool
    {
        $inventoryId = (int) $inventory->getKey();
        $branchId = (int) $inventory->branch_id;

        if ($inventoryId <= 0 || $branchId <= 0) {
            return false;
        }

        $currentQuantity = round((float) $inventory->quantity, 3);

        $line = InventoryAuditLine::query()
            ->where('branch_id', $branchId)
            ->where('inventory_id', $inventoryId)
            ->where('status', InventoryAuditLineStatus::Pending)
            ->whereHas(
                'inventoryAudit',
                fn ($query) => $query->where('status', InventoryAuditStatus::Open),
            )
            ->lockForUpdate()
            ->first();

        if (! $line instanceof InventoryAuditLine) {
            return false;
        }

        $snapshot = round((float) $line->system_quantity, 3);

        if (abs($currentQuantity - $snapshot) <= 0.0001) {
            return false;
        }

        $line->forceFill([
            'system_quantity' => $currentQuantity,
        ])->save();

        return true;
    }
}
