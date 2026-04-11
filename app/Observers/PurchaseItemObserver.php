<?php

namespace App\Observers;

use App\Models\PurchaseItem;
use App\Services\Inventory\PurchaseItemInventoryReceiptService;
use Illuminate\Support\Facades\Auth;

final class PurchaseItemObserver
{
    public function __construct(
        private readonly PurchaseItemInventoryReceiptService $receiptService,
    ) {}

    public function saved(PurchaseItem $purchaseItem): void
    {
        if ($purchaseItem->wasRecentlyCreated) {
            $delta = (float) $purchaseItem->quantity_ordered;

            if (abs($delta) < 0.0001) {
                return;
            }

            $this->receiptService->applyQuantityDelta($purchaseItem, $delta, Auth::user());

            return;
        }

        if (! $purchaseItem->wasChanged('quantity_ordered')) {
            return;
        }

        $previous = (float) ($purchaseItem->getOriginal('quantity_ordered') ?? 0);
        $current = (float) $purchaseItem->quantity_ordered;
        $delta = $current - $previous;

        if (abs($delta) < 0.0001) {
            return;
        }

        $this->receiptService->applyQuantityDelta($purchaseItem, $delta, Auth::user());
    }

    public function deleted(PurchaseItem $purchaseItem): void
    {
        $qty = (float) $purchaseItem->quantity_ordered;

        if (abs($qty) < 0.0001) {
            return;
        }

        $this->receiptService->applyQuantityDelta($purchaseItem, -1 * $qty, Auth::user());
    }
}
