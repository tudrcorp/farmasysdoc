<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Inventory\InventoryAdjustmentReason;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class InventoryAdjustmentApplyService
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     *
     * @throws ValidationException
     */
    public function apply(
        int $branchId,
        string $reason,
        array $items,
        ?string $notes,
        ?Authenticatable $actor = null,
    ): void {
        if ($branchId <= 0) {
            throw ValidationException::withMessages([
                'branch_id' => 'Sucursal inválida.',
            ]);
        }

        $this->assertActorMayApplyToBranch($branchId, $actor);

        $sign = InventoryAdjustmentReason::quantitySign($reason);
        if ($sign === 0) {
            throw ValidationException::withMessages([
                'reason' => 'Tipo de ajuste inválido.',
            ]);
        }

        $actorLabel = self::actorLabel($actor);
        $reasonLabel = InventoryAdjustmentReason::label($reason);

        $appliedItems = [];

        DB::transaction(function () use (
            $branchId,
            $reason,
            $items,
            $notes,
            $actorLabel,
            $sign,
            $reasonLabel,
            &$appliedItems,
        ): void {
            foreach ($items as $i => $row) {
                $productId = (int) ($row['product_id'] ?? 0);
                $qty = (float) ($row['quantity'] ?? 0);
                $unitCost = (float) ($row['unit_cost_snapshot'] ?? 0);
                $categoryId = (int) ($row['product_category_id'] ?? 0);

                if ($productId <= 0) {
                    throw ValidationException::withMessages([
                        'items.'.$i.'.product_id' => 'Producto inválido.',
                    ]);
                }

                if ($qty <= 0.0001) {
                    throw ValidationException::withMessages([
                        'items.'.$i.'.quantity' => 'Indique una cantidad mayor a cero.',
                    ]);
                }

                $product = Product::query()
                    ->whereKey($productId)
                    ->first();

                if (! $product instanceof Product) {
                    throw ValidationException::withMessages([
                        'items.'.$i.'.product_id' => 'Producto no encontrado en el sistema.',
                    ]);
                }

                $productCost = round(max(0.0, (float) ($product->cost_price ?? 0)), 2);

                // Si el producto no tiene costo, pedir costo + categoría para calcular venta/catálogo.
                $needsManualCost = $productCost <= 0.00001;
                if ($needsManualCost) {
                    if ($unitCost <= 0.0001) {
                        throw ValidationException::withMessages([
                            'items.'.$i.'.unit_cost_snapshot' => 'Para este producto, indique un costo unitario.',
                        ]);
                    }
                    if ($categoryId <= 0) {
                        throw ValidationException::withMessages([
                            'items.'.$i.'.product_category_id' => 'Para este producto, seleccione una categoría.',
                        ]);
                    }

                    $this->updateProductCostAndCategory(
                        product: $product,
                        unitCost: $unitCost,
                        categoryId: $categoryId,
                        actorLabel: $actorLabel,
                    );

                    $productCost = round(max(0.0, (float) $unitCost), 2);
                } else {
                    // Si el usuario no envía costo manualmente, usar el costo existente.
                    if ($unitCost <= 0.0001) {
                        $unitCost = $productCost;
                    } else {
                        // Guardar como "máximo" si el costo candidato es mayor al existente.
                        if ($unitCost > $productCost) {
                            $product->forceFill([
                                'cost_price' => round(max(0.0, $unitCost), 2),
                                'updated_by' => $actorLabel,
                            ])->saveQuietly();
                            $productCost = round(max(0.0, $unitCost), 2);
                        }
                    }
                }

                $delta = round(abs($qty) * $sign, 3);

                $inventory = Inventory::query()
                    ->where('branch_id', $branchId)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory instanceof Inventory) {
                    $inventory = new Inventory([
                        'branch_id' => $branchId,
                        'product_id' => $productId,
                        'quantity' => 0.0,
                        'reserved_quantity' => 0.0,
                        'allow_negative_stock' => false,
                        'product_category_id' => $product->product_category_id !== null ? (int) $product->product_category_id : null,
                        'cost_price' => $productCost,
                        'created_by' => $actorLabel,
                        'updated_by' => $actorLabel,
                    ]);
                    $inventory->save();
                    $inventory = Inventory::query()->whereKey($inventory->getKey())->lockForUpdate()->first();
                }

                if (! $inventory instanceof Inventory) {
                    throw new RuntimeException('No se pudo preparar inventario para el ajuste.');
                }

                $nextQuantity = round((float) $inventory->quantity + $delta, 3);

                if ($delta < 0 && ! (bool) ($inventory->allow_negative_stock ?? false) && $nextQuantity < -0.0001) {
                    throw ValidationException::withMessages([
                        'items.'.$i.'.quantity' => 'No hay existencia suficiente para aplicar la salida. Stock actual: '.(string) $inventory->quantity,
                    ]);
                }

                $inventory->forceFill([
                    'quantity' => $nextQuantity,
                    'cost_price' => round(max(0.0, $productCost), 2),
                    'last_movement_at' => now(),
                    'updated_by' => $actorLabel,
                ])->save();

                // Asegurar que el snapshot financiero quede consistente con costo/categoría.
                $inventory->syncFinancialSnapshotFromRelatedProductAndCost();
                $inventory->save();

                $movement = InventoryMovement::query()->create([
                    'product_id' => $productId,
                    'inventory_id' => (int) $inventory->getKey(),
                    'movement_type' => InventoryMovementType::Adjustment,
                    'quantity' => $delta > 0 ? abs($delta) : -1 * abs($delta),
                    'unit_cost' => $productCost > 0 ? $productCost : null,
                    'reference_type' => null,
                    'reference_id' => null,
                    'notes' => 'Ajuste inventario · '.$reasonLabel,
                    'created_by' => $actorLabel,
                ]);

                InventoryAdjustment::query()->create([
                    'purchase_id' => null,
                    'branch_id' => $branchId,
                    'product_id' => $productId,
                    'inventory_id' => (int) $inventory->getKey(),
                    'inventory_movement_id' => (int) $movement->getKey(),
                    'quantity_delta' => $delta,
                    'unit_cost_snapshot' => round(max(0.0, $productCost), 2),
                    'reason' => $reason,
                    'notes' => filled($notes) ? trim($notes) : null,
                    'created_by' => $actorLabel,
                ]);

                $appliedItems[] = [
                    'product_id' => $productId,
                    'product_name' => (string) ($product->name ?? ''),
                    'quantity_delta' => $delta,
                    'unit_cost_snapshot' => round(max(0.0, $productCost), 2),
                    'cost_was_missing' => $needsManualCost,
                    'category_id' => $categoryId > 0 ? $categoryId : (int) ($product->product_category_id ?? 0),
                ];
            }
        });

        AuditLogger::record(
            event: 'inventory_adjustment_applied',
            description: 'Ajuste de inventario aplicado · '.$reasonLabel,
            properties: [
                'module' => 'inventory_adjustments',
                'branch_id' => $branchId,
                'reason' => $reason,
                'reason_label' => $reasonLabel,
                'notes' => filled($notes) ? trim($notes) : null,
                'items_count' => count($appliedItems),
                'items' => $appliedItems,
            ],
        );
    }

    private function assertActorMayApplyToBranch(int $branchId, ?Authenticatable $actor): void
    {
        if (! $actor instanceof User) {
            return;
        }

        if ($actor->isAdministrator()) {
            return;
        }

        $permittedIds = $actor->restrictedBranchIdsForQueries();
        if ($permittedIds === [] || ! in_array($branchId, $permittedIds, true)) {
            throw ValidationException::withMessages([
                'branch_id' => 'No tiene permiso para ajustar inventario en esta sucursal.',
            ]);
        }
    }

    private function updateProductCostAndCategory(
        Product $product,
        float $unitCost,
        int $categoryId,
        string $actorLabel,
    ): void {
        $category = ProductCategory::query()
            ->whereKey($categoryId)
            ->where('is_active', true)
            ->first();

        if (! $category instanceof ProductCategory) {
            throw ValidationException::withMessages([
                'product_category_id' => 'Categoría inválida (o inactiva).',
            ]);
        }

        $product->forceFill([
            'cost_price' => round(max(0.0, $unitCost), 2),
            'product_category_id' => (int) $categoryId,
            'updated_by' => $actorLabel,
        ])->saveQuietly();
    }

    private static function actorLabel(?Authenticatable $actor = null): string
    {
        if (! $actor instanceof User) {
            return 'sistema';
        }

        return $actor->email
            ?? $actor->name
            ?? 'sistema';
    }
}
