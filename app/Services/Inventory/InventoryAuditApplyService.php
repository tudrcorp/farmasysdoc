<?php

namespace App\Services\Inventory;

use App\Enums\InventoryAuditLineStatus;
use App\Enums\InventoryAuditStatus;
use App\Enums\InventoryMovementType;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditLine;
use App\Models\InventoryAuditUpdate;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Inventory\InventoryAuditLetterRange;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class InventoryAuditApplyService
{
    public function open(
        int $branchId,
        ?Authenticatable $actor = null,
        ?string $notes = null,
        bool $truncateBranchUpdates = false,
        ?int $productCategoryId = null,
        ?string $letterFrom = null,
        ?string $letterTo = null,
    ): InventoryAudit {
        if ($branchId <= 0) {
            throw ValidationException::withMessages([
                'branch_id' => 'Sucursal inválida.',
            ]);
        }

        $this->assertActorMayAccessBranch($branchId, $actor);

        $branch = Branch::query()->whereKey($branchId)->first();
        if (! $branch instanceof Branch) {
            throw ValidationException::withMessages([
                'branch_id' => 'Sucursal no encontrada.',
            ]);
        }

        $resolvedCategoryId = $this->resolveProductCategoryId($productCategoryId);
        $letterRange = InventoryAuditLetterRange::resolve($letterFrom, $letterTo);

        $openExists = InventoryAudit::query()
            ->where('branch_id', $branchId)
            ->where('status', InventoryAuditStatus::Open)
            ->exists();

        if ($openExists) {
            throw ValidationException::withMessages([
                'branch_id' => 'Ya existe una auditoría abierta para esta sucursal.',
            ]);
        }

        $actorId = $actor instanceof User ? (int) $actor->getKey() : null;

        return DB::transaction(function () use (
            $branchId,
            $actor,
            $actorId,
            $notes,
            $truncateBranchUpdates,
            $resolvedCategoryId,
            $letterRange,
        ): InventoryAudit {
            if ($truncateBranchUpdates) {
                $this->truncateUpdatesForBranch($branchId, $actor);
            }

            $audit = InventoryAudit::query()->create([
                'branch_id' => $branchId,
                'product_category_id' => $resolvedCategoryId,
                'letter_from' => $letterRange[0] ?? null,
                'letter_to' => $letterRange[1] ?? null,
                'status' => InventoryAuditStatus::Open,
                'started_by' => $actorId,
                'started_at' => now(),
                'notes' => filled($notes) ? trim((string) $notes) : null,
            ]);

            $now = now();
            $rows = [];
            $skippedOrphans = 0;

            $this->scopedInventoriesQuery($branchId, $resolvedCategoryId, $letterRange)
                ->with(['product:id,cost_price,name'])
                ->orderBy('id')
                ->chunkById(500, function ($inventories) use ($audit, $branchId, $now, &$rows, &$skippedOrphans): void {
                    foreach ($inventories as $inventory) {
                        if (! $inventory instanceof Inventory) {
                            continue;
                        }

                        // Defensa extra: no insertar product_id huérfano (rompe FK).
                        if (! $inventory->product instanceof Product || (int) $inventory->product_id <= 0) {
                            $skippedOrphans++;

                            continue;
                        }

                        $productCost = round(max(0.0, (float) ($inventory->product->cost_price ?? $inventory->cost_price ?? 0)), 2);

                        $rows[] = [
                            'inventory_audit_id' => (int) $audit->getKey(),
                            'inventory_id' => (int) $inventory->getKey(),
                            'product_id' => (int) $inventory->product_id,
                            'branch_id' => $branchId,
                            'status' => InventoryAuditLineStatus::Pending->value,
                            'system_quantity' => round((float) $inventory->quantity, 3),
                            'system_cost_price' => $productCost,
                            'cost_changed' => false,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        if (count($rows) >= 500) {
                            InventoryAuditLine::query()->insert($rows);
                            $rows = [];
                        }
                    }
                });

            if ($rows !== []) {
                InventoryAuditLine::query()->insert($rows);
            }

            $linesCount = $audit->lines()->count();

            if ($linesCount === 0) {
                throw ValidationException::withMessages([
                    'branch_id' => 'No hay productos con inventario que coincidan con la categoría y el rango de letras seleccionados.',
                ]);
            }

            AuditLogger::record(
                event: 'inventory_audit_opened',
                description: 'Auditoría de inventario abierta',
                auditableType: InventoryAudit::class,
                auditableId: $audit->getKey(),
                properties: [
                    'module' => 'inventory_audits',
                    'branch_id' => $branchId,
                    'product_category_id' => $resolvedCategoryId,
                    'letter_from' => $letterRange[0] ?? null,
                    'letter_to' => $letterRange[1] ?? null,
                    'lines_count' => $linesCount,
                    'skipped_orphan_inventories' => $skippedOrphans,
                ],
                user: $actor instanceof User ? $actor : null,
            );

            return $audit->fresh(['branch', 'productCategory']) ?? $audit;
        });
    }

    private function resolveProductCategoryId(?int $productCategoryId): ?int
    {
        if ($productCategoryId === null || $productCategoryId <= 0) {
            return null;
        }

        $exists = ProductCategory::query()
            ->whereKey($productCategoryId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'product_category_id' => 'Categoría de inventario no válida.',
            ]);
        }

        return $productCategoryId;
    }

    /**
     * @param  array{0: string, 1: string}|null  $letterRange
     * @return Builder<Inventory>
     */
    private function scopedInventoriesQuery(int $branchId, ?int $productCategoryId, ?array $letterRange): Builder
    {
        return Inventory::query()
            ->where('branch_id', $branchId)
            ->whereExists(function ($query) use ($productCategoryId, $letterRange): void {
                $query->selectRaw('1')
                    ->from('products')
                    ->whereColumn('products.id', 'inventories.product_id');

                if ($productCategoryId !== null) {
                    $query->where('products.product_category_id', $productCategoryId);
                }

                if ($letterRange !== null) {
                    [$from, $to] = $letterRange;
                    $query->whereRaw(
                        'UPPER(LEFT(TRIM(products.name), 1)) BETWEEN ? AND ?',
                        [$from, $to],
                    );
                }
            });
    }

    public function verifyWithoutChanges(
        InventoryAuditLine $line,
        ?Authenticatable $actor = null,
    ): InventoryAuditLine {
        return DB::transaction(function () use ($line, $actor): InventoryAuditLine {
            $line = InventoryAuditLine::query()
                ->whereKey($line->getKey())
                ->lockForUpdate()
                ->first();

            if (! $line instanceof InventoryAuditLine) {
                throw new RuntimeException('Línea de auditoría no encontrada.');
            }

            $this->assertLineIsProcessable($line, $actor);

            $inventory = Inventory::query()
                ->whereKey($line->inventory_id)
                ->lockForUpdate()
                ->first();

            if (! $inventory instanceof Inventory) {
                throw ValidationException::withMessages([
                    'line' => 'Inventario no encontrado para esta línea.',
                ]);
            }

            $this->assertQuantityNotDiverged($line, $inventory);

            $actorId = $actor instanceof User ? (int) $actor->getKey() : null;
            $actorLabel = self::actorLabel($actor);

            $inventory->forceFill([
                'last_stock_take_at' => now(),
                'updated_by' => $actorLabel,
            ])->save();

            $line->forceFill([
                'status' => InventoryAuditLineStatus::Verified,
                'counted_quantity' => round((float) $inventory->quantity, 3),
                'quantity_delta' => 0,
                'cost_changed' => false,
                'new_cost_price' => null,
                'processed_by' => $actorId,
                'processed_at' => now(),
            ])->save();

            AuditLogger::record(
                event: 'inventory_audit_line_verified',
                description: 'Producto auditado sin modificaciones',
                auditableType: InventoryAuditLine::class,
                auditableId: $line->getKey(),
                properties: [
                    'module' => 'inventory_audits',
                    'inventory_audit_id' => (int) $line->inventory_audit_id,
                    'product_id' => (int) $line->product_id,
                    'branch_id' => (int) $line->branch_id,
                ],
                user: $actor instanceof User ? $actor : null,
            );

            return $line->fresh() ?? $line;
        });
    }

    /**
     * @param  array{counted_quantity: float|int|string, new_cost_price?: float|int|string|null}  $data
     */
    public function applyUpdate(
        InventoryAuditLine $line,
        array $data,
        ?Authenticatable $actor = null,
    ): InventoryAuditLine {
        $countedRaw = $data['counted_quantity'] ?? null;
        if ($countedRaw === null || $countedRaw === '') {
            throw ValidationException::withMessages([
                'counted_quantity' => 'Indique la cantidad contada.',
            ]);
        }

        $countedQuantity = round((float) $countedRaw, 3);
        if ($countedQuantity < -0.0001) {
            throw ValidationException::withMessages([
                'counted_quantity' => 'La cantidad contada no puede ser negativa.',
            ]);
        }

        $costProvided = array_key_exists('new_cost_price', $data)
            && $data['new_cost_price'] !== null
            && $data['new_cost_price'] !== '';

        $newCostPrice = $costProvided ? round(max(0.0, (float) $data['new_cost_price']), 2) : null;

        return DB::transaction(function () use ($line, $countedQuantity, $costProvided, $newCostPrice, $actor): InventoryAuditLine {
            $line = InventoryAuditLine::query()
                ->whereKey($line->getKey())
                ->lockForUpdate()
                ->first();

            if (! $line instanceof InventoryAuditLine) {
                throw new RuntimeException('Línea de auditoría no encontrada.');
            }

            $this->assertLineIsProcessable($line, $actor);

            $inventory = Inventory::query()
                ->whereKey($line->inventory_id)
                ->lockForUpdate()
                ->first();

            if (! $inventory instanceof Inventory) {
                throw ValidationException::withMessages([
                    'line' => 'Inventario no encontrado para esta línea.',
                ]);
            }

            $this->assertQuantityNotDiverged($line, $inventory);

            $product = Product::query()
                ->whereKey($line->product_id)
                ->lockForUpdate()
                ->first();

            if (! $product instanceof Product) {
                throw ValidationException::withMessages([
                    'line' => 'Producto no encontrado.',
                ]);
            }

            $previousQuantity = round((float) $inventory->quantity, 3);
            $previousCost = round(max(0.0, (float) ($product->cost_price ?? 0)), 2);
            $quantityDelta = round($countedQuantity - $previousQuantity, 3);

            $costChanged = $costProvided && abs(($newCostPrice ?? 0) - $previousCost) > 0.00001;
            $quantityChanged = abs($quantityDelta) > 0.0001;

            if (! $quantityChanged && ! $costChanged) {
                throw ValidationException::withMessages([
                    'counted_quantity' => 'No hay cambios que aplicar. Use «Sin modificaciones» o indique una cantidad/costo distinto.',
                ]);
            }

            $actorId = $actor instanceof User ? (int) $actor->getKey() : null;
            $actorLabel = self::actorLabel($actor);
            $movementId = null;

            if ($quantityChanged) {
                if ($quantityDelta < 0 && ! (bool) ($inventory->allow_negative_stock ?? false) && $countedQuantity < -0.0001) {
                    throw ValidationException::withMessages([
                        'counted_quantity' => 'La cantidad contada no puede ser negativa.',
                    ]);
                }

                $inventory->forceFill([
                    'quantity' => $countedQuantity,
                    'last_movement_at' => now(),
                    'last_stock_take_at' => now(),
                    'updated_by' => $actorLabel,
                ])->save();

                $movement = InventoryMovement::query()->create([
                    'product_id' => (int) $product->getKey(),
                    'inventory_id' => (int) $inventory->getKey(),
                    'movement_type' => InventoryMovementType::StockTake,
                    'quantity' => $quantityDelta,
                    'unit_cost' => $costChanged ? $newCostPrice : ($previousCost > 0 ? $previousCost : null),
                    'reference_type' => $line->getMorphClass(),
                    'reference_id' => $line->getKey(),
                    'notes' => 'Toma física · auditoría de inventario #'.$line->inventory_audit_id,
                    'created_by' => $actorLabel,
                ]);
                $movementId = (int) $movement->getKey();
            } else {
                $inventory->forceFill([
                    'last_stock_take_at' => now(),
                    'updated_by' => $actorLabel,
                ])->save();
            }

            if ($costChanged && $newCostPrice !== null) {
                $product->forceFill([
                    'cost_price' => $newCostPrice,
                    'updated_by' => $actorLabel,
                ])->save();
            }

            $line->forceFill([
                'status' => InventoryAuditLineStatus::Updated,
                'counted_quantity' => $countedQuantity,
                'new_cost_price' => $costChanged ? $newCostPrice : null,
                'quantity_delta' => $quantityDelta,
                'cost_changed' => $costChanged,
                'inventory_movement_id' => $movementId,
                'processed_by' => $actorId,
                'processed_at' => now(),
            ])->save();

            $branch = Branch::query()->whereKey($line->branch_id)->first();

            InventoryAuditUpdate::query()->create([
                'inventory_audit_id' => (int) $line->inventory_audit_id,
                'inventory_audit_line_id' => (int) $line->getKey(),
                'branch_id' => (int) $line->branch_id,
                'product_id' => (int) $product->getKey(),
                'product_sku' => $product->sku,
                'product_barcode' => $product->barcode,
                'product_name' => (string) $product->name,
                'branch_name' => (string) ($branch?->name ?? ''),
                'previous_quantity' => $previousQuantity,
                'new_quantity' => $countedQuantity,
                'quantity_delta' => $quantityDelta,
                'previous_cost_price' => $previousCost,
                'new_cost_price' => $costChanged ? $newCostPrice : $previousCost,
                'quantity_changed' => $quantityChanged,
                'cost_changed' => $costChanged,
                'processed_by' => $actorId,
                'processed_by_name' => $actor instanceof User
                    ? ($actor->name ?? $actor->email ?? 'usuario')
                    : 'sistema',
                'processed_at' => now(),
            ]);

            AuditLogger::record(
                event: 'inventory_audit_line_applied',
                description: 'Producto actualizado en auditoría de inventario',
                auditableType: InventoryAuditLine::class,
                auditableId: $line->getKey(),
                properties: [
                    'module' => 'inventory_audits',
                    'inventory_audit_id' => (int) $line->inventory_audit_id,
                    'product_id' => (int) $product->getKey(),
                    'branch_id' => (int) $line->branch_id,
                    'previous_quantity' => $previousQuantity,
                    'new_quantity' => $countedQuantity,
                    'quantity_delta' => $quantityDelta,
                    'previous_cost_price' => $previousCost,
                    'new_cost_price' => $costChanged ? $newCostPrice : null,
                    'quantity_changed' => $quantityChanged,
                    'cost_changed' => $costChanged,
                ],
                user: $actor instanceof User ? $actor : null,
            );

            return $line->fresh() ?? $line;
        });
    }

    public function close(
        InventoryAudit $audit,
        ?Authenticatable $actor = null,
    ): InventoryAudit {
        return DB::transaction(function () use ($audit, $actor): InventoryAudit {
            $audit = InventoryAudit::query()
                ->whereKey($audit->getKey())
                ->lockForUpdate()
                ->first();

            if (! $audit instanceof InventoryAudit) {
                throw new RuntimeException('Auditoría no encontrada.');
            }

            $this->assertActorMayAccessBranch((int) $audit->branch_id, $actor);

            if (! $audit->isOpen()) {
                throw ValidationException::withMessages([
                    'audit' => 'La auditoría ya está cerrada.',
                ]);
            }

            if ($audit->pendingLinesCount() > 0) {
                throw ValidationException::withMessages([
                    'audit' => 'No se puede cerrar: aún hay productos pendientes por procesar.',
                ]);
            }

            $actorId = $actor instanceof User ? (int) $actor->getKey() : null;

            $audit->forceFill([
                'status' => InventoryAuditStatus::Closed,
                'closed_by' => $actorId,
                'closed_at' => now(),
            ])->save();

            AuditLogger::record(
                event: 'inventory_audit_closed',
                description: 'Auditoría de inventario cerrada',
                auditableType: InventoryAudit::class,
                auditableId: $audit->getKey(),
                properties: [
                    'module' => 'inventory_audits',
                    'branch_id' => (int) $audit->branch_id,
                ],
                user: $actor instanceof User ? $actor : null,
            );

            return $audit->fresh() ?? $audit;
        });
    }

    public function truncateUpdatesForBranch(
        int $branchId,
        ?Authenticatable $actor = null,
    ): int {
        $this->assertActorMayAccessBranch($branchId, $actor);

        $deleted = InventoryAuditUpdate::query()
            ->where('branch_id', $branchId)
            ->delete();

        AuditLogger::record(
            event: 'inventory_audit_updates_truncated',
            description: 'Reporte de productos actualizados truncado',
            properties: [
                'module' => 'inventory_audits',
                'branch_id' => $branchId,
                'deleted_count' => $deleted,
            ],
            user: $actor instanceof User ? $actor : null,
        );

        return $deleted;
    }

    private function assertLineIsProcessable(InventoryAuditLine $line, ?Authenticatable $actor): void
    {
        $this->assertActorMayAccessBranch((int) $line->branch_id, $actor);

        $audit = InventoryAudit::query()->whereKey($line->inventory_audit_id)->first();
        if (! $audit instanceof InventoryAudit || ! $audit->isOpen()) {
            throw ValidationException::withMessages([
                'line' => 'La auditoría no está abierta.',
            ]);
        }

        if (! $line->isPending()) {
            throw ValidationException::withMessages([
                'line' => 'Esta línea ya fue procesada.',
            ]);
        }
    }

    private function assertQuantityNotDiverged(InventoryAuditLine $line, Inventory $inventory): void
    {
        $current = round((float) $inventory->quantity, 3);
        $snapshot = round((float) $line->system_quantity, 3);

        if (abs($current - $snapshot) > 0.0001) {
            $line->forceFill([
                'system_quantity' => $current,
            ])->save();

            throw ValidationException::withMessages([
                'counted_quantity' => 'La existencia del sistema cambió desde que se abrió la auditoría (ahora: '.$current.'). Vuelva a contar e intente de nuevo.',
            ]);
        }
    }

    private function assertActorMayAccessBranch(int $branchId, ?Authenticatable $actor): void
    {
        if (! $actor instanceof User) {
            return;
        }

        if ($actor->isAdministrator()) {
            return;
        }

        if (! $actor->isManager()) {
            throw ValidationException::withMessages([
                'branch_id' => 'Solo gerentes pueden ejecutar auditorías de inventario.',
            ]);
        }

        $permittedIds = $actor->restrictedBranchIdsForQueries();
        if ($permittedIds === [] || ! in_array($branchId, $permittedIds, true)) {
            throw ValidationException::withMessages([
                'branch_id' => 'No tiene permiso para auditar inventario en esta sucursal.',
            ]);
        }
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
