<?php

use App\Enums\InventoryAuditLineStatus;
use App\Enums\InventoryAuditStatus;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditLine;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryAuditApplyService;
use App\Services\Inventory\InventoryAuditOpenLineSyncService;

function inventoryAuditOpenLineSyncSetup(): array
{
    $branch = Branch::factory()->create();
    $manager = User::factory()->create([
        'branch_id' => $branch->id,
        'roles' => ['GERENTE'],
    ]);
    $product = Product::factory()->create([
        'cost_price' => 10.00,
        'sale_price' => 15.00,
    ]);
    $inventory = Inventory::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 0,
        'cost_price' => 10.00,
    ]);

    return compact('branch', 'manager', 'product', 'inventory');
}

it('actualiza el snapshot pendiente cuando cambia la existencia por una factura', function () {
    ['branch' => $branch, 'manager' => $manager, 'inventory' => $inventory] = inventoryAuditOpenLineSyncSetup();

    $audit = app(InventoryAuditApplyService::class)->open((int) $branch->id, $manager);
    $line = $audit->lines()->firstOrFail();

    expect((float) $line->system_quantity)->toBe(0.0);

    $inventory->forceFill(['quantity' => 2])->save();

    $line->refresh();

    expect((float) $line->system_quantity)->toBe(2.0)
        ->and($line->status)->toBe(InventoryAuditLineStatus::Pending);
});

it('no altera lineas ya procesadas al cambiar la existencia', function () {
    ['branch' => $branch, 'manager' => $manager, 'inventory' => $inventory] = inventoryAuditOpenLineSyncSetup();
    $service = app(InventoryAuditApplyService::class);

    $audit = $service->open((int) $branch->id, $manager);
    $line = $audit->lines()->firstOrFail();
    $service->verifyWithoutChanges($line, $manager);

    $inventory->forceFill(['quantity' => 5])->save();

    $line->refresh();

    expect($line->status)->toBe(InventoryAuditLineStatus::Verified)
        ->and((float) $line->system_quantity)->toBe(0.0)
        ->and((float) $line->counted_quantity)->toBe(0.0);
});

it('realinea snapshots pendientes al reabrir la pantalla de trabajo', function () {
    ['branch' => $branch, 'manager' => $manager, 'inventory' => $inventory] = inventoryAuditOpenLineSyncSetup();

    $audit = app(InventoryAuditApplyService::class)->open((int) $branch->id, $manager);
    $line = $audit->lines()->firstOrFail();

    $inventory->forceFill(['quantity' => 2])->saveQuietly();

    $updated = app(InventoryAuditOpenLineSyncService::class)
        ->refreshPendingLinesForAudit($audit->fresh() ?? $audit);

    $line->refresh();

    expect($updated)->toBe(1)
        ->and((float) $line->system_quantity)->toBe(2.0)
        ->and($line->status)->toBe(InventoryAuditLineStatus::Pending);
});

it('no realinea lineas de una auditoria cerrada', function () {
    ['branch' => $branch, 'inventory' => $inventory] = inventoryAuditOpenLineSyncSetup();

    $audit = InventoryAudit::factory()->closed()->create([
        'branch_id' => $branch->id,
        'status' => InventoryAuditStatus::Closed,
    ]);
    InventoryAuditLine::factory()->pending()->create([
        'inventory_audit_id' => $audit->id,
        'inventory_id' => $inventory->id,
        'product_id' => $inventory->product_id,
        'branch_id' => $branch->id,
        'system_quantity' => 0,
    ]);

    $inventory->forceFill(['quantity' => 9])->saveQuietly();

    $updated = app(InventoryAuditOpenLineSyncService::class)->refreshPendingLinesForAudit($audit);

    expect($updated)->toBe(0);
});
