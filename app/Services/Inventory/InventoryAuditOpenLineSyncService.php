<?php

namespace App\Services\Inventory;

use App\Enums\InventoryAuditLineStatus;
use App\Enums\InventoryAuditStatus;
use App\Models\Inventory;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditLine;

/**
 * Mantiene alineada la existencia de sistema en líneas pendientes de una auditoría abierta
 * cuando el stock cambia por compras, ventas, anulaciones, traslados u otros movimientos.
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
            ->first();

        if (! $line instanceof InventoryAuditLine) {
            return false;
        }

        return $this->writeSnapshotIfDiverged($line, $currentQuantity);
    }

    /**
     * Realinea todas las líneas pendientes de una auditoría abierta con el stock actual.
     */
    public function refreshPendingLinesForAudit(InventoryAudit $audit): int
    {
        if (! $audit->isOpen()) {
            return 0;
        }

        $auditId = (int) $audit->getKey();
        if ($auditId <= 0) {
            return 0;
        }

        $updated = 0;

        InventoryAuditLine::query()
            ->where('inventory_audit_id', $auditId)
            ->where('status', InventoryAuditLineStatus::Pending)
            ->orderBy('id')
            ->chunkById(200, function ($lines) use (&$updated): void {
                $inventoryIds = $lines->pluck('inventory_id')->filter()->unique()->values()->all();
                if ($inventoryIds === []) {
                    return;
                }

                $quantities = Inventory::query()
                    ->whereIn('id', $inventoryIds)
                    ->pluck('quantity', 'id');

                foreach ($lines as $line) {
                    if (! $line instanceof InventoryAuditLine) {
                        continue;
                    }

                    $currentRaw = $quantities->get($line->inventory_id);
                    if ($currentRaw === null) {
                        continue;
                    }

                    if ($this->writeSnapshotIfDiverged($line, round((float) $currentRaw, 3))) {
                        $updated++;
                    }
                }
            });

        return $updated;
    }

    private function writeSnapshotIfDiverged(InventoryAuditLine $line, float $currentQuantity): bool
    {
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
