<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryLotBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductTransfer;
use App\Support\Purchases\LotExpirationMonthYear;

/**
 * Traslados entre sucursales con trazabilidad FEFO: descuenta lotes en origen y los acredita en destino.
 */
final class FefoLotTransferDispatchService
{
    public function __construct(
        private readonly FefoLotBalanceQueryService $fefoQuery,
    ) {}

    public function dispatchForTransferLine(
        int $fromBranchId,
        int $toBranchId,
        Product $product,
        float $quantity,
        Inventory $fromInventory,
        ?Inventory $toInventory,
        ProductTransfer $transfer,
        string $actorLabel,
        bool $creditDestinationInventory = true,
    ): void {
        if ($quantity <= 0.0001 || ! $product->requires_expiry_on_purchase) {
            return;
        }

        $remaining = round($quantity, 3);
        $unitCost = round(max(0.0, (float) ($product->cost_price ?? 0)), 2);
        $balances = $this->fefoQuery->fefoBalancesWithLotsForProduct($fromBranchId, (int) $product->getKey());

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
            $this->decrementOriginLotBalance($locked, $take);
            if ($creditDestinationInventory) {
                $this->creditDestinationLotBalance($toBranchId, $locked, $take);
            }

            $lot = $locked->productLot ?? $balance->productLot;
            $lotLabel = $lot?->expiration_month_year ?? '—';
            $invoiceRef = trim((string) ($lot?->supplier_invoice_number ?? ''));
            $expiryDate = $lot !== null
                ? LotExpirationMonthYear::toEndOfMonthDate($lot->expiration_month_year)
                : null;

            InventoryMovement::query()->create([
                'product_id' => $product->getKey(),
                'inventory_id' => $fromInventory->getKey(),
                'movement_type' => InventoryMovementType::Transfer,
                'quantity' => -1 * abs($take),
                'unit_cost' => $unitCost > 0 ? $unitCost : null,
                'batch_number' => $invoiceRef !== '' ? $invoiceRef : null,
                'expiry_date' => $expiryDate,
                'reference_type' => ProductTransfer::class,
                'reference_id' => $transfer->getKey(),
                'notes' => 'Salida traslado '.$transfer->code.' · Lote '.$lotLabel,
                'created_by' => $actorLabel,
            ]);

            if ($toInventory instanceof Inventory) {
                InventoryMovement::query()->create([
                    'product_id' => $product->getKey(),
                    'inventory_id' => $toInventory->getKey(),
                    'movement_type' => InventoryMovementType::Transfer,
                    'quantity' => abs($take),
                    'unit_cost' => $unitCost > 0 ? $unitCost : null,
                    'batch_number' => $invoiceRef !== '' ? $invoiceRef : null,
                    'expiry_date' => $expiryDate,
                    'reference_type' => ProductTransfer::class,
                    'reference_id' => $transfer->getKey(),
                    'notes' => 'Entrada traslado '.$transfer->code.' · Lote '.$lotLabel,
                    'created_by' => $actorLabel,
                ]);
            }

            $remaining = round($remaining - $take, 3);
        }

        if ($remaining > 0.0001) {
            $this->createGenericTransferMovements(
                $product,
                $fromInventory,
                $toInventory,
                $transfer,
                $remaining,
                $unitCost,
                $actorLabel,
                ' (saldo sin lote asignado)',
            );
        }
    }

    private function decrementOriginLotBalance(InventoryLotBalance $balance, float $take): void
    {
        $nextBalance = round((float) $balance->quantity_remaining - $take, 3);

        if ($nextBalance <= 0.0001) {
            $balance->delete();

            return;
        }

        $balance->forceFill(['quantity_remaining' => $nextBalance])->save();
    }

    private function creditDestinationLotBalance(int $toBranchId, InventoryLotBalance $originBalance, float $take): void
    {
        $destinationBalance = InventoryLotBalance::query()->firstOrCreate(
            [
                'branch_id' => $toBranchId,
                'product_lot_id' => $originBalance->product_lot_id,
            ],
            [
                'product_id' => (int) $originBalance->product_id,
                'quantity_remaining' => 0,
            ],
        );

        $destinationBalance->forceFill([
            'product_id' => (int) $originBalance->product_id,
            'quantity_remaining' => round((float) $destinationBalance->quantity_remaining + $take, 3),
        ])->save();
    }

    private function createGenericTransferMovements(
        Product $product,
        Inventory $fromInventory,
        ?Inventory $toInventory,
        ProductTransfer $transfer,
        float $quantity,
        float $unitCost,
        string $actorLabel,
        string $noteSuffix = '',
    ): void {
        InventoryMovement::query()->create([
            'product_id' => $product->getKey(),
            'inventory_id' => $fromInventory->getKey(),
            'movement_type' => InventoryMovementType::Transfer,
            'quantity' => -1 * abs($quantity),
            'unit_cost' => $unitCost > 0 ? $unitCost : null,
            'reference_type' => ProductTransfer::class,
            'reference_id' => $transfer->getKey(),
            'notes' => 'Salida traslado '.$transfer->code.$noteSuffix,
            'created_by' => $actorLabel,
        ]);

        if (! $toInventory instanceof Inventory) {
            return;
        }

        InventoryMovement::query()->create([
            'product_id' => $product->getKey(),
            'inventory_id' => $toInventory->getKey(),
            'movement_type' => InventoryMovementType::Transfer,
            'quantity' => abs($quantity),
            'unit_cost' => $unitCost > 0 ? $unitCost : null,
            'reference_type' => ProductTransfer::class,
            'reference_id' => $transfer->getKey(),
            'notes' => 'Entrada traslado '.$transfer->code.$noteSuffix,
            'created_by' => $actorLabel,
        ]);
    }
}
