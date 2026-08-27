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
    public function __construct(
        private readonly InventoryAuditOtpService $otpService,
    ) {}

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

    /**
     * Marca la línea como verificada sin alterar existencias.
     * Si el stock cambió durante la auditoría (compra, venta, traslado, etc.),
     * realinea el snapshot y confirma la existencia actual.
     */
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

            $snapshotRefreshed = $this->syncLineSnapshotFromInventory($line, $inventory);
            $currentQuantity = round((float) $inventory->quantity, 3);

            $actorId = $actor instanceof User ? (int) $actor->getKey() : null;
            $actorLabel = self::actorLabel($actor);

            $inventory->forceFill([
                'last_stock_take_at' => now(),
                'updated_by' => $actorLabel,
            ])->save();

            $line->forceFill([
                'status' => InventoryAuditLineStatus::Verified,
                'counted_quantity' => $currentQuantity,
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
                    'counted_quantity' => $currentQuantity,
                    'system_quantity_refreshed' => $snapshotRefreshed,
                ],
                user: $actor instanceof User ? $actor : null,
            );

            return $line->fresh() ?? $line;
        });
    }

    /**
     * Línea del producto en una auditoría abierta de la sucursal (si existe).
     */
    public function findOpenAuditLineForProduct(int $branchId, int $productId): ?InventoryAuditLine
    {
        if ($branchId <= 0 || $productId <= 0) {
            return null;
        }

        return InventoryAuditLine::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->whereHas('inventoryAudit', function ($query): void {
                $query->where('status', InventoryAuditStatus::Open);
            })
            ->with(['inventoryAudit:id,status,branch_id'])
            ->first();
    }

    /**
     * Quita una línea pendiente de una auditoría abierta para permitir Auditoría Express.
     */
    public function removePendingLineFromOpenAudit(
        InventoryAuditLine $line,
        ?Authenticatable $actor = null,
    ): void {
        DB::transaction(function () use ($line, $actor): void {
            $line = InventoryAuditLine::query()
                ->whereKey($line->getKey())
                ->lockForUpdate()
                ->first();

            if (! $line instanceof InventoryAuditLine) {
                throw ValidationException::withMessages([
                    'product_id' => 'La línea de auditoría ya no existe.',
                ]);
            }

            $this->assertActorMayAccessBranch((int) $line->branch_id, $actor);

            $audit = InventoryAudit::query()
                ->whereKey($line->inventory_audit_id)
                ->lockForUpdate()
                ->first();

            if (! $audit instanceof InventoryAudit || ! $audit->isOpen()) {
                throw ValidationException::withMessages([
                    'product_id' => 'La auditoría ya no está abierta.',
                ]);
            }

            if (! $line->isPending()) {
                throw ValidationException::withMessages([
                    'product_id' => 'El producto ya fue procesado en la auditoría abierta #'.$audit->getKey().'. Cierre esa auditoría antes de usar Auditoría Express.',
                ]);
            }

            $lineId = (int) $line->getKey();
            $auditId = (int) $audit->getKey();
            $productId = (int) $line->product_id;
            $branchId = (int) $line->branch_id;

            $line->delete();

            AuditLogger::record(
                event: 'inventory_audit_line_removed_for_express',
                description: 'Producto quitado de auditoría abierta para Auditoría Express',
                auditableType: InventoryAudit::class,
                auditableId: $auditId,
                properties: [
                    'module' => 'inventory_audits',
                    'inventory_audit_id' => $auditId,
                    'inventory_audit_line_id' => $lineId,
                    'product_id' => $productId,
                    'branch_id' => $branchId,
                    'reason' => 'express_audit',
                ],
                user: $actor instanceof User ? $actor : null,
            );
        });
    }

    /**
     * Auditoría individual (Express): misma lógica de actualización, fuera de un ciclo masivo.
     *
     * @param  array{
     *     counted_quantity: float|int|string,
     *     new_cost_price?: float|int|string|null,
     *     product_category_id?: int|string|null,
     *     otp_code?: string|null
     * }  $data
     */
    public function applyExpress(
        int $branchId,
        int $productId,
        array $data,
        ?Authenticatable $actor = null,
    ): InventoryAudit {
        if ($branchId <= 0) {
            throw ValidationException::withMessages([
                'branch_id' => 'Sucursal inválida.',
            ]);
        }

        if ($productId <= 0) {
            throw ValidationException::withMessages([
                'product_id' => 'Seleccione un producto.',
            ]);
        }

        $parsed = $this->parseApplyUpdatePayload($data);
        $otpCode = isset($data['otp_code']) ? (string) $data['otp_code'] : null;

        return DB::transaction(function () use ($branchId, $productId, $parsed, $actor, $otpCode): InventoryAudit {
            $this->assertActorMayAccessBranch($branchId, $actor);

            $blockingLine = $this->findOpenAuditLineForProduct($branchId, $productId);
            if ($blockingLine instanceof InventoryAuditLine) {
                throw ValidationException::withMessages([
                    'product_id' => 'Este producto está en la auditoría abierta #'.$blockingLine->inventory_audit_id.'. Quítelo de esa auditoría para auditarlo de forma individual.',
                ]);
            }

            $inventory = Inventory::query()
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $inventory instanceof Inventory) {
                throw ValidationException::withMessages([
                    'product_id' => 'El producto no tiene inventario en la sucursal seleccionada.',
                ]);
            }

            $product = Product::query()
                ->whereKey($productId)
                ->lockForUpdate()
                ->first();

            if (! $product instanceof Product) {
                throw ValidationException::withMessages([
                    'product_id' => 'Producto no encontrado.',
                ]);
            }

            $this->assertManagerOtpIfRequired(
                actor: $actor,
                otpCode: $otpCode,
                inventory: $inventory,
                product: $product,
                countedQuantity: $parsed['counted_quantity'],
                costProvided: $parsed['cost_provided'],
                newCostPrice: $parsed['new_cost_price'],
                categoryProvided: $parsed['category_provided'],
                requestedCategoryId: $parsed['requested_category_id'],
            );

            $actorId = $actor instanceof User ? (int) $actor->getKey() : null;
            $actorLabel = self::actorLabel($actor);
            $now = now();

            $audit = InventoryAudit::query()->create([
                'branch_id' => $branchId,
                'product_category_id' => $product->product_category_id !== null
                    ? (int) $product->product_category_id
                    : null,
                'status' => InventoryAuditStatus::Closed,
                'started_by' => $actorId,
                'started_at' => $now,
                'closed_by' => $actorId,
                'closed_at' => $now,
                'notes' => 'Auditoría Express',
            ]);

            $systemCost = round(max(0.0, (float) ($product->cost_price ?? 0)), 2);

            $line = InventoryAuditLine::query()->create([
                'inventory_audit_id' => (int) $audit->getKey(),
                'inventory_id' => (int) $inventory->getKey(),
                'product_id' => $productId,
                'branch_id' => $branchId,
                'status' => InventoryAuditLineStatus::Pending,
                'system_quantity' => round((float) $inventory->quantity, 3),
                'system_cost_price' => $systemCost,
                'cost_changed' => false,
            ]);

            $result = $this->applyCountedChangesToInventoryAndProduct(
                inventory: $inventory,
                product: $product,
                countedQuantity: $parsed['counted_quantity'],
                costProvided: $parsed['cost_provided'],
                newCostPrice: $parsed['new_cost_price'],
                categoryProvided: $parsed['category_provided'],
                requestedCategoryId: $parsed['requested_category_id'],
                actorLabel: $actorLabel,
                movementNotes: 'Toma física · auditoría express #'.$audit->getKey(),
                referenceType: $line->getMorphClass(),
                referenceId: $line->getKey(),
                noChangesMessage: 'No hay cambios que aplicar. Indique una cantidad, costo o categoría distinta.',
            );

            $line->forceFill([
                'status' => InventoryAuditLineStatus::Updated,
                'counted_quantity' => $result['counted_quantity'],
                'new_cost_price' => $result['cost_changed'] ? $result['new_cost_price'] : null,
                'quantity_delta' => $result['quantity_delta'],
                'cost_changed' => $result['cost_changed'],
                'inventory_movement_id' => $result['movement_id'],
                'processed_by' => $actorId,
                'processed_at' => $now,
            ])->save();

            $branch = Branch::query()->whereKey($branchId)->first();

            InventoryAuditUpdate::query()->create([
                'inventory_audit_id' => (int) $audit->getKey(),
                'inventory_audit_line_id' => (int) $line->getKey(),
                'branch_id' => $branchId,
                'product_id' => (int) $product->getKey(),
                'product_sku' => $product->sku,
                'product_barcode' => $product->barcode,
                'product_name' => (string) $product->name,
                'branch_name' => (string) ($branch?->name ?? ''),
                'previous_quantity' => $result['previous_quantity'],
                'new_quantity' => $result['counted_quantity'],
                'quantity_delta' => $result['quantity_delta'],
                'previous_cost_price' => $result['previous_cost'],
                'new_cost_price' => $result['cost_changed'] ? $result['new_cost_price'] : $result['previous_cost'],
                'quantity_changed' => $result['quantity_changed'],
                'cost_changed' => $result['cost_changed'],
                'processed_by' => $actorId,
                'processed_by_name' => $actor instanceof User
                    ? ($actor->name ?? $actor->email ?? 'usuario')
                    : 'sistema',
                'processed_at' => $now,
            ]);

            AuditLogger::record(
                event: 'inventory_audit_express_applied',
                description: 'Auditoría Express aplicada',
                auditableType: InventoryAudit::class,
                auditableId: $audit->getKey(),
                properties: [
                    'module' => 'inventory_audits',
                    'mode' => 'express',
                    'inventory_audit_id' => (int) $audit->getKey(),
                    'inventory_audit_line_id' => (int) $line->getKey(),
                    'product_id' => (int) $product->getKey(),
                    'branch_id' => $branchId,
                    'previous_quantity' => $result['previous_quantity'],
                    'new_quantity' => $result['counted_quantity'],
                    'quantity_delta' => $result['quantity_delta'],
                    'previous_cost_price' => $result['previous_cost'],
                    'new_cost_price' => $result['cost_changed'] ? $result['new_cost_price'] : null,
                    'quantity_changed' => $result['quantity_changed'],
                    'cost_changed' => $result['cost_changed'],
                    'category_changed' => $result['category_changed'],
                    'previous_product_category_id' => $result['previous_category_id'],
                    'new_product_category_id' => $result['category_changed']
                        ? $result['new_category_id']
                        : $result['previous_category_id'],
                ],
                user: $actor instanceof User ? $actor : null,
            );

            return $audit->fresh(['branch', 'productCategory']) ?? $audit;
        });
    }

    /**
     * @param  array{
     *     counted_quantity: float|int|string,
     *     new_cost_price?: float|int|string|null,
     *     product_category_id?: int|string|null,
     *     otp_code?: string|null
     * }  $data
     */
    public function applyUpdate(
        InventoryAuditLine $line,
        array $data,
        ?Authenticatable $actor = null,
    ): InventoryAuditLine {
        $parsed = $this->parseApplyUpdatePayload($data);
        $otpCode = isset($data['otp_code']) ? (string) $data['otp_code'] : null;

        return DB::transaction(function () use (
            $line,
            $parsed,
            $actor,
            $otpCode,
        ): InventoryAuditLine {
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

            $this->assertManagerOtpIfRequired(
                actor: $actor,
                otpCode: $otpCode,
                inventory: $inventory,
                product: $product,
                countedQuantity: $parsed['counted_quantity'],
                costProvided: $parsed['cost_provided'],
                newCostPrice: $parsed['new_cost_price'],
                categoryProvided: $parsed['category_provided'],
                requestedCategoryId: $parsed['requested_category_id'],
            );

            $actorId = $actor instanceof User ? (int) $actor->getKey() : null;
            $actorLabel = self::actorLabel($actor);

            $result = $this->applyCountedChangesToInventoryAndProduct(
                inventory: $inventory,
                product: $product,
                countedQuantity: $parsed['counted_quantity'],
                costProvided: $parsed['cost_provided'],
                newCostPrice: $parsed['new_cost_price'],
                categoryProvided: $parsed['category_provided'],
                requestedCategoryId: $parsed['requested_category_id'],
                actorLabel: $actorLabel,
                movementNotes: 'Toma física · auditoría de inventario #'.$line->inventory_audit_id,
                referenceType: $line->getMorphClass(),
                referenceId: $line->getKey(),
                noChangesMessage: 'No hay cambios que aplicar. Use «Sin modificaciones» o indique una cantidad, costo o categoría distinta.',
            );

            $line->forceFill([
                'status' => InventoryAuditLineStatus::Updated,
                'counted_quantity' => $result['counted_quantity'],
                'new_cost_price' => $result['cost_changed'] ? $result['new_cost_price'] : null,
                'quantity_delta' => $result['quantity_delta'],
                'cost_changed' => $result['cost_changed'],
                'inventory_movement_id' => $result['movement_id'],
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
                'previous_quantity' => $result['previous_quantity'],
                'new_quantity' => $result['counted_quantity'],
                'quantity_delta' => $result['quantity_delta'],
                'previous_cost_price' => $result['previous_cost'],
                'new_cost_price' => $result['cost_changed'] ? $result['new_cost_price'] : $result['previous_cost'],
                'quantity_changed' => $result['quantity_changed'],
                'cost_changed' => $result['cost_changed'],
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
                    'previous_quantity' => $result['previous_quantity'],
                    'new_quantity' => $result['counted_quantity'],
                    'quantity_delta' => $result['quantity_delta'],
                    'previous_cost_price' => $result['previous_cost'],
                    'new_cost_price' => $result['cost_changed'] ? $result['new_cost_price'] : null,
                    'quantity_changed' => $result['quantity_changed'],
                    'cost_changed' => $result['cost_changed'],
                    'category_changed' => $result['category_changed'],
                    'previous_product_category_id' => $result['previous_category_id'],
                    'new_product_category_id' => $result['category_changed']
                        ? $result['new_category_id']
                        : $result['previous_category_id'],
                ],
                user: $actor instanceof User ? $actor : null,
            );

            return $line->fresh() ?? $line;
        });
    }

    /**
     * @param  array{
     *     counted_quantity: float|int|string,
     *     new_cost_price?: float|int|string|null,
     *     product_category_id?: int|string|null
     * }  $data
     * @return array{
     *     counted_quantity: float,
     *     cost_provided: bool,
     *     new_cost_price: float|null,
     *     category_provided: bool,
     *     requested_category_id: int|null
     * }
     */
    private function parseApplyUpdatePayload(array $data): array
    {
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

        $categoryProvided = array_key_exists('product_category_id', $data)
            && $data['product_category_id'] !== null
            && $data['product_category_id'] !== '';

        $requestedCategoryId = $categoryProvided ? (int) $data['product_category_id'] : null;
        if ($categoryProvided) {
            $this->assertProductCategoryIsActive($requestedCategoryId);
        }

        return [
            'counted_quantity' => $countedQuantity,
            'cost_provided' => $costProvided,
            'new_cost_price' => $newCostPrice,
            'category_provided' => $categoryProvided,
            'requested_category_id' => $requestedCategoryId,
        ];
    }

    private function assertManagerOtpIfRequired(
        ?Authenticatable $actor,
        ?string $otpCode,
        Inventory $inventory,
        Product $product,
        float $countedQuantity,
        bool $costProvided,
        ?float $newCostPrice,
        bool $categoryProvided,
        ?int $requestedCategoryId,
    ): void {
        if (! $actor instanceof User || ! $this->otpService->actorRequiresOtp($actor)) {
            return;
        }

        if (! $this->payloadHasSensitiveChanges(
            inventory: $inventory,
            product: $product,
            countedQuantity: $countedQuantity,
            costProvided: $costProvided,
            newCostPrice: $newCostPrice,
            categoryProvided: $categoryProvided,
            requestedCategoryId: $requestedCategoryId,
        )) {
            return;
        }

        $this->otpService->verifyAndConsume($actor, $otpCode);
    }

    private function payloadHasSensitiveChanges(
        Inventory $inventory,
        Product $product,
        float $countedQuantity,
        bool $costProvided,
        ?float $newCostPrice,
        bool $categoryProvided,
        ?int $requestedCategoryId,
    ): bool {
        $previousQuantity = round((float) $inventory->quantity, 3);
        $previousCategoryId = $product->product_category_id !== null
            ? (int) $product->product_category_id
            : null;

        $quantityChanged = abs(round($countedQuantity - $previousQuantity, 3)) > 0.0001;
        $costChanged = $this->resolveCostChange($inventory, $product, $costProvided, $newCostPrice)['cost_changed'];
        $categoryChanged = $categoryProvided
            && $requestedCategoryId !== null
            && $requestedCategoryId !== $previousCategoryId;

        return $quantityChanged || $costChanged || $categoryChanged;
    }

    /**
     * @return array{
     *     previous_quantity: float,
     *     counted_quantity: float,
     *     quantity_delta: float,
     *     quantity_changed: bool,
     *     previous_cost: float,
     *     new_cost_price: float|null,
     *     cost_changed: bool,
     *     previous_category_id: int|null,
     *     new_category_id: int|null,
     *     category_changed: bool,
     *     movement_id: int|null
     * }
     */
    private function applyCountedChangesToInventoryAndProduct(
        Inventory $inventory,
        Product $product,
        float $countedQuantity,
        bool $costProvided,
        ?float $newCostPrice,
        bool $categoryProvided,
        ?int $requestedCategoryId,
        string $actorLabel,
        string $movementNotes,
        ?string $referenceType,
        mixed $referenceId,
        string $noChangesMessage,
    ): array {
        $previousQuantity = round((float) $inventory->quantity, 3);
        $previousProductCost = round(max(0.0, (float) ($product->cost_price ?? 0)), 2);
        $costChange = $this->resolveCostChange($inventory, $product, $costProvided, $newCostPrice);
        $previousCost = $costChange['previous_cost'];
        $costChanged = $costChange['cost_changed'];
        $previousCategoryId = $product->product_category_id !== null
            ? (int) $product->product_category_id
            : null;
        $quantityDelta = round($countedQuantity - $previousQuantity, 3);

        $quantityChanged = abs($quantityDelta) > 0.0001;
        $categoryChanged = $categoryProvided
            && $requestedCategoryId !== null
            && $requestedCategoryId !== $previousCategoryId;

        if (! $quantityChanged && ! $costChanged && ! $categoryChanged) {
            throw ValidationException::withMessages([
                'counted_quantity' => $noChangesMessage,
            ]);
        }

        $movementId = null;
        $inventoryFill = [
            'last_stock_take_at' => now(),
            'updated_by' => $actorLabel,
        ];

        if ($quantityChanged) {
            if ($quantityDelta < 0 && ! (bool) ($inventory->allow_negative_stock ?? false) && $countedQuantity < -0.0001) {
                throw ValidationException::withMessages([
                    'counted_quantity' => 'La cantidad contada no puede ser negativa.',
                ]);
            }

            $inventoryFill['quantity'] = $countedQuantity;
            $inventoryFill['last_movement_at'] = now();
        }

        if ($costChanged && $newCostPrice !== null) {
            $inventoryFill['cost_price'] = $newCostPrice;
        }

        $inventory->forceFill($inventoryFill)->save();

        if ($quantityChanged) {
            $movement = InventoryMovement::query()->create([
                'product_id' => (int) $product->getKey(),
                'inventory_id' => (int) $inventory->getKey(),
                'movement_type' => InventoryMovementType::StockTake,
                'quantity' => $quantityDelta,
                'unit_cost' => $costChanged ? $newCostPrice : ($previousCost > 0 ? $previousCost : null),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $movementNotes,
                'created_by' => $actorLabel,
            ]);
            $movementId = (int) $movement->getKey();
        }

        $productUpdates = [];

        if ($categoryChanged && $requestedCategoryId !== null) {
            $productUpdates['product_category_id'] = $requestedCategoryId;
        }

        if ($costChanged && $newCostPrice !== null && abs($newCostPrice - $previousProductCost) > 0.00001) {
            $productUpdates['cost_price'] = $newCostPrice;
        }

        if ($productUpdates !== []) {
            $productUpdates['updated_by'] = $actorLabel;
            $product->forceFill($productUpdates)->save();
        }

        return [
            'previous_quantity' => $previousQuantity,
            'counted_quantity' => $countedQuantity,
            'quantity_delta' => $quantityDelta,
            'quantity_changed' => $quantityChanged,
            'previous_cost' => $previousCost,
            'new_cost_price' => $costChanged ? $newCostPrice : null,
            'cost_changed' => $costChanged,
            'previous_category_id' => $previousCategoryId,
            'new_category_id' => $categoryChanged ? $requestedCategoryId : $previousCategoryId,
            'category_changed' => $categoryChanged,
            'movement_id' => $movementId,
        ];
    }

    /**
     * @return array{previous_cost: float, cost_changed: bool}
     */
    private function resolveCostChange(
        Inventory $inventory,
        Product $product,
        bool $costProvided,
        ?float $newCostPrice,
    ): array {
        $previousProductCost = round(max(0.0, (float) ($product->cost_price ?? 0)), 2);
        $previousInventoryCost = round(max(0.0, (float) ($inventory->cost_price ?? 0)), 2);
        $previousCost = $previousInventoryCost > 0.00001
            ? $previousInventoryCost
            : $previousProductCost;

        $costChanged = $costProvided
            && $newCostPrice !== null
            && (
                abs($newCostPrice - $previousProductCost) > 0.00001
                || abs($newCostPrice - $previousInventoryCost) > 0.00001
            );

        return [
            'previous_cost' => $previousCost,
            'cost_changed' => $costChanged,
        ];
    }

    private function assertProductCategoryIsActive(?int $productCategoryId): void
    {
        if ($productCategoryId === null || $productCategoryId <= 0) {
            throw ValidationException::withMessages([
                'product_category_id' => 'Seleccione una categoría válida.',
            ]);
        }

        $exists = ProductCategory::query()
            ->whereKey($productCategoryId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'product_category_id' => 'Categoría inválida (o inactiva).',
            ]);
        }
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

    private function syncLineSnapshotFromInventory(InventoryAuditLine $line, Inventory $inventory): bool
    {
        $current = round((float) $inventory->quantity, 3);
        $snapshot = round((float) $line->system_quantity, 3);

        if (abs($current - $snapshot) <= 0.0001) {
            return false;
        }

        $line->forceFill([
            'system_quantity' => $current,
        ])->save();

        return true;
    }

    private function assertQuantityNotDiverged(InventoryAuditLine $line, Inventory $inventory): void
    {
        if (! $this->syncLineSnapshotFromInventory($line, $inventory)) {
            return;
        }

        $current = round((float) $inventory->quantity, 3);

        throw ValidationException::withMessages([
            'counted_quantity' => 'La existencia del sistema cambió desde que se abrió la auditoría (ahora: '.$current.'). Vuelva a contar e intente de nuevo.',
        ]);
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
