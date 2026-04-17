<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
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
    public function applyQuantityDelta(PurchaseItem $item, float $delta, ?Authenticatable $actor = null): void
    {
        if (abs($delta) < 0.0001) {
            return;
        }

        $productId = (int) $item->product_id;
        if ($productId <= 0) {
            return;
        }

        $purchaseId = (int) $item->purchase_id;
        if ($purchaseId <= 0) {
            return;
        }

        $actorLabel = self::actorLabel($actor);

        DB::transaction(function () use ($item, $delta, $productId, $purchaseId, $actorLabel): void {
            $purchase = Purchase::query()
                ->whereKey($purchaseId)
                ->lockForUpdate()
                ->first();

            if (! $purchase instanceof Purchase) {
                return;
            }

            $branchId = (int) $purchase->branch_id;
            if ($branchId <= 0) {
                return;
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

            $nextQuantity = round((float) $inventory->quantity + $delta, 3);

            if ($delta < 0 && ! $inventory->allow_negative_stock && $nextQuantity < -0.0001) {
                throw ValidationException::withMessages([
                    'items' => 'No hay existencia suficiente para reducir la cantidad en compra de: '.$product->name.'. Stock actual: '.(string) $inventory->quantity.'.',
                ]);
            }

            $unitCost = max(0.0, (float) ($item->unit_cost ?? 0));

            $inventory->quantity = $nextQuantity;
            $inventory->cost_price = $unitCost;
            $inventory->syncFinancialSnapshotFromRelatedProductAndCost();
            $inventory->last_movement_at = now();
            $inventory->updated_by = $actorLabel;
            $inventory->save();

            $lineNo = (int) ($item->line_number ?? 0);
            $lineSuffix = $lineNo > 0 ? " · Línea #{$lineNo}" : '';

            InventoryMovement::query()->create([
                'product_id' => $productId,
                'inventory_id' => $inventory->getKey(),
                'movement_type' => InventoryMovementType::Purchase,
                'quantity' => $delta > 0 ? abs($delta) : -1 * abs($delta),
                'unit_cost' => $unitCost > 0 ? $unitCost : null,
                'reference_type' => PurchaseItem::class,
                'reference_id' => $item->getKey(),
                'notes' => 'Compra '.$purchase->purchase_number.$lineSuffix,
                'created_by' => $actorLabel,
            ]);

            if ($item->exists && $item->inventory_id === null) {
                $item->forceFill(['inventory_id' => $inventory->getKey()])->saveQuietly();
            }
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
