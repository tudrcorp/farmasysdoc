<?php

namespace App\Observers;

use App\Models\Inventory;
use App\Services\Inventory\InventoryAuditOpenLineSyncService;

final class InventoryObserver
{
    public function __construct(
        private readonly InventoryAuditOpenLineSyncService $openAuditLineSync,
    ) {}

    public function updated(Inventory $inventory): void
    {
        if (! $inventory->wasChanged('quantity')) {
            return;
        }

        $this->openAuditLineSync->syncPendingLineForInventory($inventory);
    }
}
