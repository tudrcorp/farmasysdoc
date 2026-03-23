<?php

use App\Enums\InventoryMovementType;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('product has inventory per branch with available quantity', function () {
    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['sku' => 'INV-001']);
    $inventory = Inventory::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 100,
        'reserved_quantity' => 5,
    ]);

    expect($product->inventories)->toHaveCount(1)
        ->and($product->inventoryForBranch($branch))->not->toBeNull()
        ->and($product->inventoryForBranch($branch)->is($inventory))->toBeTrue()
        ->and((float) $inventory->available_quantity)->toBe(95.0);
});

test('inventory movements are linked to product and optional inventory', function () {
    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['sku' => 'INV-002']);
    $inventory = Inventory::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity' => 50,
    ]);

    $movement = InventoryMovement::factory()->create([
        'product_id' => $product->id,
        'inventory_id' => $inventory->id,
        'movement_type' => InventoryMovementType::Purchase,
        'quantity' => 25,
    ]);

    expect($product->inventoryMovements)->toHaveCount(1)
        ->and($movement->product->is($product))->toBeTrue()
        ->and($movement->inventory?->is($inventory))->toBeTrue()
        ->and($movement->movement_type)->toBe(InventoryMovementType::Purchase);
});

test('same product can have separate inventories per branch', function () {
    $branchA = Branch::factory()->create(['code' => 'SUC-A']);
    $branchB = Branch::factory()->create(['code' => 'SUC-B']);
    $product = Product::factory()->create(['sku' => 'INV-MULTI']);

    $invA = Inventory::factory()->create([
        'branch_id' => $branchA->id,
        'product_id' => $product->id,
        'quantity' => 10,
    ]);
    $invB = Inventory::factory()->create([
        'branch_id' => $branchB->id,
        'product_id' => $product->id,
        'quantity' => 20,
    ]);

    expect($product->inventories)->toHaveCount(2)
        ->and($product->inventoryForBranch($branchA)->is($invA))->toBeTrue()
        ->and($product->inventoryForBranch($branchB)->is($invB))->toBeTrue();
});

test('duplicate inventory for same product and branch is not allowed', function () {
    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['sku' => 'INV-003']);

    Inventory::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
    ]);

    expect(fn () => Inventory::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
    ]))->toThrow(QueryException::class);
});
