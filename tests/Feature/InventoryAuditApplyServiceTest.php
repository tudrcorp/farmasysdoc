<?php

use App\Enums\InventoryAuditLineStatus;
use App\Enums\InventoryAuditStatus;
use App\Enums\InventoryMovementType;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryAuditLine;
use App\Models\InventoryAuditUpdate;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryAuditApplyService;
use Illuminate\Validation\ValidationException;

function inventoryAuditManager(Branch $branch): User
{
    return User::factory()->create([
        'branch_id' => $branch->id,
        'roles' => ['GERENTE'],
    ]);
}

function inventoryAuditSetup(): array
{
    $branch = Branch::factory()->create();
    $manager = inventoryAuditManager($branch);
    $product = Product::factory()->create([
        'cost_price' => 10.00,
        'sale_price' => 15.00,
    ]);
    $inventory = Inventory::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 20,
        'cost_price' => 10.00,
    ]);

    return compact('branch', 'manager', 'product', 'inventory');
}

it('abre una auditoria con lineas pendientes por inventario de la sucursal', function () {
    ['branch' => $branch, 'manager' => $manager, 'inventory' => $inventory, 'product' => $product] = inventoryAuditSetup();

    $audit = app(InventoryAuditApplyService::class)->open(
        branchId: (int) $branch->id,
        actor: $manager,
    );

    expect($audit->status)->toBe(InventoryAuditStatus::Open)
        ->and($audit->lines)->toHaveCount(1);

    $line = $audit->lines->first();
    expect($line)->toBeInstanceOf(InventoryAuditLine::class)
        ->and($line->status)->toBe(InventoryAuditLineStatus::Pending)
        ->and((float) $line->system_quantity)->toBe(20.0)
        ->and((float) $line->system_cost_price)->toBe(10.0)
        ->and((int) $line->inventory_id)->toBe((int) $inventory->id)
        ->and((int) $line->product_id)->toBe((int) $product->id);
});

it('rechaza abrir una segunda auditoria abierta en la misma sucursal', function () {
    ['branch' => $branch, 'manager' => $manager] = inventoryAuditSetup();
    $service = app(InventoryAuditApplyService::class);

    $service->open((int) $branch->id, $manager);

    expect(fn () => $service->open((int) $branch->id, $manager))
        ->toThrow(ValidationException::class);
});

it('omite inventarios huerfanos sin producto al abrir la auditoria', function () {
    ['branch' => $branch, 'manager' => $manager] = inventoryAuditSetup();

    // inventories no tiene FK a products: se puede crear un huerfano.
    Inventory::query()->create([
        'branch_id' => $branch->id,
        'product_id' => 999999991,
        'quantity' => 3,
        'reserved_quantity' => 0,
        'allow_negative_stock' => false,
    ]);

    $audit = app(InventoryAuditApplyService::class)->open((int) $branch->id, $manager);

    expect($audit->lines)->toHaveCount(1)
        ->and($audit->lines->first()?->product_id)->not->toBe(999999991);
});

it('marca producto como procesado sin modificaciones', function () {
    ['branch' => $branch, 'manager' => $manager, 'inventory' => $inventory] = inventoryAuditSetup();
    $service = app(InventoryAuditApplyService::class);
    $audit = $service->open((int) $branch->id, $manager);
    $line = $audit->lines()->firstOrFail();

    $service->verifyWithoutChanges($line, $manager);

    $line->refresh();
    $inventory->refresh();

    expect($line->status)->toBe(InventoryAuditLineStatus::Verified)
        ->and((float) $line->quantity_delta)->toBe(0.0)
        ->and(InventoryAuditUpdate::query()->count())->toBe(0)
        ->and($inventory->last_stock_take_at)->not->toBeNull()
        ->and((float) $inventory->quantity)->toBe(20.0);
});

it('actualiza existencia y registra movimiento toma fisica y fila de reporte', function () {
    ['branch' => $branch, 'manager' => $manager, 'inventory' => $inventory, 'product' => $product] = inventoryAuditSetup();
    $service = app(InventoryAuditApplyService::class);
    $audit = $service->open((int) $branch->id, $manager);
    $line = $audit->lines()->firstOrFail();

    $service->applyUpdate($line, [
        'counted_quantity' => 17,
    ], $manager);

    $line->refresh();
    $inventory->refresh();

    expect($line->status)->toBe(InventoryAuditLineStatus::Updated)
        ->and((float) $line->counted_quantity)->toBe(17.0)
        ->and((float) $line->quantity_delta)->toBe(-3.0)
        ->and((float) $inventory->quantity)->toBe(17.0);

    $movement = InventoryMovement::query()->where('inventory_id', $inventory->id)->latest('id')->first();
    expect($movement)->not->toBeNull()
        ->and($movement->movement_type)->toBe(InventoryMovementType::StockTake)
        ->and((float) $movement->quantity)->toBe(-3.0);

    $update = InventoryAuditUpdate::query()->first();
    expect($update)->not->toBeNull()
        ->and((int) $update->product_id)->toBe((int) $product->id)
        ->and($update->quantity_changed)->toBeTrue()
        ->and($update->cost_changed)->toBeFalse()
        ->and((float) $update->previous_quantity)->toBe(20.0)
        ->and((float) $update->new_quantity)->toBe(17.0);
});

it('permite bajar el costo del producto durante la auditoria', function () {
    ['branch' => $branch, 'manager' => $manager, 'product' => $product] = inventoryAuditSetup();
    $service = app(InventoryAuditApplyService::class);
    $audit = $service->open((int) $branch->id, $manager);
    $line = $audit->lines()->firstOrFail();

    $service->applyUpdate($line, [
        'counted_quantity' => 20,
        'new_cost_price' => 7.50,
    ], $manager);

    $product->refresh();
    $line->refresh();
    $update = InventoryAuditUpdate::query()->first();

    expect((float) $product->cost_price)->toBe(7.50)
        ->and($line->cost_changed)->toBeTrue()
        ->and($update?->cost_changed)->toBeTrue()
        ->and((float) $update->previous_cost_price)->toBe(10.0)
        ->and((float) $update->new_cost_price)->toBe(7.50);
});

it('confirma sin modificaciones con el stock actual si cambio durante la auditoria', function () {
    ['branch' => $branch, 'manager' => $manager, 'inventory' => $inventory] = inventoryAuditSetup();
    $service = app(InventoryAuditApplyService::class);
    $audit = $service->open((int) $branch->id, $manager);
    $line = $audit->lines()->firstOrFail();

    $inventory->forceFill(['quantity' => 2])->saveQuietly();

    $service->verifyWithoutChanges($line, $manager);

    $line->refresh();
    $inventory->refresh();

    expect($line->status)->toBe(InventoryAuditLineStatus::Verified)
        ->and((float) $line->system_quantity)->toBe(2.0)
        ->and((float) $line->counted_quantity)->toBe(2.0)
        ->and((float) $line->quantity_delta)->toBe(0.0)
        ->and((float) $inventory->quantity)->toBe(2.0)
        ->and(InventoryAuditUpdate::query()->count())->toBe(0);
});

it('confirma sin modificaciones despues de sincronizar el stock de una factura', function () {
    ['branch' => $branch, 'manager' => $manager, 'inventory' => $inventory] = inventoryAuditSetup();
    $service = app(InventoryAuditApplyService::class);
    $audit = $service->open((int) $branch->id, $manager);
    $line = $audit->lines()->firstOrFail();

    $inventory->forceFill(['quantity' => 2])->save();

    $line->refresh();
    expect((float) $line->system_quantity)->toBe(2.0);

    $service->verifyWithoutChanges($line, $manager);

    $line->refresh();
    $inventory->refresh();

    expect($line->status)->toBe(InventoryAuditLineStatus::Verified)
        ->and((float) $line->counted_quantity)->toBe(2.0)
        ->and((float) $inventory->quantity)->toBe(2.0);
});

it('rechaza aplicar si la existencia divergio desde el snapshot', function () {
    ['branch' => $branch, 'manager' => $manager, 'inventory' => $inventory] = inventoryAuditSetup();
    $service = app(InventoryAuditApplyService::class);
    $audit = $service->open((int) $branch->id, $manager);
    $line = $audit->lines()->firstOrFail();

    $inventory->forceFill(['quantity' => 15])->saveQuietly();

    expect(fn () => $service->applyUpdate($line, [
        'counted_quantity' => 14,
    ], $manager))->toThrow(ValidationException::class);

    $line->refresh();
    expect((float) $line->system_quantity)->toBe(15.0)
        ->and($line->status)->toBe(InventoryAuditLineStatus::Pending);
});

it('no cierra si quedan lineas pendientes', function () {
    ['branch' => $branch, 'manager' => $manager] = inventoryAuditSetup();
    $service = app(InventoryAuditApplyService::class);
    $audit = $service->open((int) $branch->id, $manager);

    expect(fn () => $service->close($audit, $manager))
        ->toThrow(ValidationException::class);
});

it('cierra cuando todas las lineas estan procesadas', function () {
    ['branch' => $branch, 'manager' => $manager] = inventoryAuditSetup();
    $service = app(InventoryAuditApplyService::class);
    $audit = $service->open((int) $branch->id, $manager);
    $line = $audit->lines()->firstOrFail();
    $service->verifyWithoutChanges($line, $manager);

    $closed = $service->close($audit, $manager);

    expect($closed->status)->toBe(InventoryAuditStatus::Closed)
        ->and($closed->closed_at)->not->toBeNull();
});

it('trunca el reporte de actualizados por sucursal', function () {
    ['branch' => $branch, 'manager' => $manager] = inventoryAuditSetup();
    $otherBranch = Branch::factory()->create();
    $otherManager = inventoryAuditManager($otherBranch);
    $service = app(InventoryAuditApplyService::class);

    $otherProduct = Product::factory()->create(['cost_price' => 4]);
    Inventory::factory()->create([
        'branch_id' => $otherBranch->id,
        'product_id' => $otherProduct->id,
        'quantity' => 5,
    ]);

    $audit = $service->open((int) $branch->id, $manager);
    $line = $audit->lines()->firstOrFail();
    $service->applyUpdate($line, ['counted_quantity' => 18], $manager);

    $otherAudit = $service->open((int) $otherBranch->id, $otherManager);
    $otherLine = $otherAudit->lines()->firstOrFail();
    $service->applyUpdate($otherLine, ['counted_quantity' => 4], $otherManager);

    expect(InventoryAuditUpdate::query()->where('branch_id', $branch->id)->count())->toBe(1)
        ->and(InventoryAuditUpdate::query()->where('branch_id', $otherBranch->id)->count())->toBe(1);

    $deleted = $service->truncateUpdatesForBranch((int) $branch->id, $manager);

    expect($deleted)->toBe(1)
        ->and(InventoryAuditUpdate::query()->where('branch_id', $branch->id)->count())->toBe(0)
        ->and(InventoryAuditUpdate::query()->where('branch_id', $otherBranch->id)->count())->toBe(1);
});

it('rechaza auditoria en sucursal no permitida para el gerente', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $manager = inventoryAuditManager($branchA);
    Inventory::factory()->create(['branch_id' => $branchB->id]);

    expect(fn () => app(InventoryAuditApplyService::class)->open((int) $branchB->id, $manager))
        ->toThrow(ValidationException::class);
});
