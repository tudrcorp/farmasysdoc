<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\PurchaseEntryCurrency;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Entrada de mercancía por línea de compra: actualiza existencias en la sucursal de la compra y registra movimiento de inventario.
 */
final class PurchaseItemInventoryReceiptService
{
    /**
     * Aplica un delta de cantidad (positivo = entrada, negativo = reverso) sobre el inventario de la sucursal de recepción.
     *
     * @throws ValidationException
     */
    public function applyQuantityDelta(
        PurchaseItem $item,
        float $delta,
        ?Authenticatable $actor = null,
        ?InventoryMovementType $movementType = null,
        ?string $movementNotesOverride = null,
    ): ?InventoryMovement {
        if (abs($delta) < 0.0001) {
            return null;
        }

        $movementType ??= InventoryMovementType::Purchase;

        $productId = (int) $item->product_id;
        if ($productId <= 0) {
            return null;
        }

        $purchaseId = (int) $item->purchase_id;
        if ($purchaseId <= 0) {
            return null;
        }

        $actorLabel = self::actorLabel($actor);

        return DB::transaction(function () use ($item, $delta, $productId, $purchaseId, $actorLabel, $movementType, $movementNotesOverride): ?InventoryMovement {
            $purchase = Purchase::query()
                ->whereKey($purchaseId)
                ->lockForUpdate()
                ->first();

            if (! $purchase instanceof Purchase) {
                return null;
            }

            $branchId = (int) $purchase->branch_id;
            if ($branchId <= 0) {
                return null;
            }

            $product = Product::query()->find($productId);
            if (! $product instanceof Product) {
                throw ValidationException::withMessages([
                    'items' => 'Producto no encontrado en una línea de compra.',
                ]);
            }

            $inventory = Inventory::query()
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $inventory instanceof Inventory) {
                $inventory = new Inventory([
                    'branch_id' => $branchId,
                    'product_id' => $productId,
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'allow_negative_stock' => false,
                    'created_by' => $actorLabel,
                    'updated_by' => $actorLabel,
                ]);
                $inventory->save();

                $inventory = Inventory::query()
                    ->whereKey($inventory->getKey())
                    ->lockForUpdate()
                    ->first();
            }

            if (! $inventory instanceof Inventory) {
                throw ValidationException::withMessages([
                    'items' => 'No se pudo obtener el inventario para: '.$product->name.'.',
                ]);
            }

            $createdMovement = null;

            $nextQuantity = round((float) $inventory->quantity + $delta, 3);

            if ($delta < 0 && ! $inventory->allow_negative_stock && $nextQuantity < -0.0001) {
                throw ValidationException::withMessages([
                    'items' => 'No hay existencia suficiente para reducir la cantidad en compra de: '.$product->name.'. Stock actual: '.(string) $inventory->quantity.'.',
                ]);
            }

            $unitCost = round(max(0.0, (float) ($item->unit_cost ?? 0)), 2);
            if ($purchase->entryCurrency() === PurchaseEntryCurrency::VES) {
                $rate = (float) ($purchase->official_usd_ves_rate ?? 0);
                if ($rate > 0) {
                    $unitCost = round(max(0.0, $unitCost / $rate), 2);
                }
            }

            $inventory->quantity = $nextQuantity;
            $inventory->cost_price = $unitCost;
            $inventory->syncFinancialSnapshotFromRelatedProductAndCost();
            $inventory->last_movement_at = now();
            $inventory->updated_by = $actorLabel;
            $inventory->save();

            $lineNo = (int) ($item->line_number ?? 0);
            $lineSuffix = $lineNo > 0 ? " · Línea #{$lineNo}" : '';

            $notes = $movementNotesOverride ?? ('Compra '.$purchase->purchase_number.$lineSuffix);

            $createdMovement = InventoryMovement::query()->create([
                'product_id' => $productId,
                'inventory_id' => $inventory->getKey(),
                'movement_type' => $movementType,
                'quantity' => $delta > 0 ? abs($delta) : -1 * abs($delta),
                'unit_cost' => $unitCost > 0 ? $unitCost : null,
                'reference_type' => PurchaseItem::class,
                'reference_id' => $item->getKey(),
                'notes' => $notes,
                'created_by' => $actorLabel,
            ]);

            if ($item->exists && $item->inventory_id === null) {
                $item->forceFill(['inventory_id' => $inventory->getKey()])->saveQuietly();
            }

            return $createdMovement;
        });
    }

    private static function actorLabel(?Authenticatable $user): string
    {
        if ($user === null) {
            return 'sistema';
        }

        return $user->email
            ?? $user->name
            ?? 'sistema';
    }
}
