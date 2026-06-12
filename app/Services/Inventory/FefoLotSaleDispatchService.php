<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryLotBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Support\Purchases\LotExpirationMonthYear;

/**
 * Descuenta existencia por lote en ventas POS siguiendo FEFO (First Expired, First Out).
 */
final class FefoLotSaleDispatchService
{
    public function __construct(
        private readonly FefoLotBalanceQueryService $fefoQuery,
    ) {}

    /**
     * Registra movimientos de inventario por lote y actualiza balances FEFO.
     */
    public function dispatchForSaleLine(
        int $branchId,
        Product $product,
        float $quantity,
        Inventory $inventory,
        Sale $sale,
        string $actorLabel,
    ): void {
        if ($quantity <= 0.0001) {
            return;
        }

        if (! $product->requires_expiry_on_purchase) {
            $this->createGenericMovement($product, $inventory, $sale, $quantity, $actorLabel);

            return;
        }

        $remaining = round($quantity, 3);
        $balances = $this->fefoQuery->fefoBalancesWithLotsForProduct($branchId, (int) $product->getKey());

        if ($balances === []) {
            $this->createGenericMovement($product, $inventory, $sale, $remaining, $actorLabel);

            return;
        }

        $unitCost = round(max(0.0, (float) ($product->cost_price ?? 0)), 2);

        foreach ($balances as $balance) {
            if ($remaining <= 0.0001) {
                break;
            }

            $locked = InventoryLotBalance::query()
                ->whereKey($balance->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof InventoryLotBalance) {
                continue;
            }

            $available = (float) $locked->quantity_remaining;
            if ($available <= 0.0001) {
                continue;
            }

            $take = min($remaining, $available);
            $nextBalance = round($available - $take, 3);

            if ($nextBalance <= 0.0001) {
                $locked->delete();
            } else {
                $locked->forceFill(['quantity_remaining' => $nextBalance])->save();
            }

            $lot = $locked->productLot ?? $balance->productLot;
            $expiryDate = $lot !== null
                ? LotExpirationMonthYear::toEndOfMonthDate($lot->expiration_month_year)
                : null;

            $lotLabel = $lot?->expiration_month_year ?? '—';
            $invoiceRef = trim((string) ($lot?->supplier_invoice_number ?? ''));

            InventoryMovement::query()->create([
                'product_id' => $product->getKey(),
                'inventory_id' => $inventory->getKey(),
                'movement_type' => InventoryMovementType::Sale,
                'quantity' => -1 * abs($take),
                'unit_cost' => $unitCost > 0 ? $unitCost : null,
                'batch_number' => $invoiceRef !== '' ? $invoiceRef : null,
                'expiry_date' => $expiryDate,
                'reference_type' => Sale::class,
                'reference_id' => $sale->getKey(),
                'notes' => 'Venta '.$sale->sale_number.' · Lote '.$lotLabel,
                'created_by' => $actorLabel,
            ]);

            $remaining = round($remaining - $take, 3);
        }

        if ($remaining > 0.0001) {
            $this->createGenericMovement(
                $product,
                $inventory,
                $sale,
                $remaining,
                $actorLabel,
                'Venta '.$sale->sale_number.' (saldo sin lote asignado)',
            );
        }
    }

    private function createGenericMovement(
        Product $product,
        Inventory $inventory,
        Sale $sale,
        float $quantity,
        string $actorLabel,
        ?string $notesOverride = null,
    ): void {
        $unitCost = round(max(0.0, (float) ($product->cost_price ?? 0)), 2);

        InventoryMovement::query()->create([
            'product_id' => $product->getKey(),
            'inventory_id' => $inventory->getKey(),
            'movement_type' => InventoryMovementType::Sale,
            'quantity' => -1 * abs($quantity),
            'unit_cost' => $unitCost > 0 ? $unitCost : null,
            'reference_type' => Sale::class,
            'reference_id' => $sale->getKey(),
            'notes' => $notesOverride ?? ('Venta '.$sale->sale_number),
            'created_by' => $actorLabel,
        ]);
    }
}
