<?php

use App\Enums\InventoryMovementType;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Database\QueryException;

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
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
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

test('inventory copies pharmacy snapshot from product on create', function () {
    $branch = Branch::factory()->create();
    $product = Product::factory()->medication()->create([
        'sku' => 'INV-SNAP-1',
        'active_ingredient' => ['Paracetamol'],
        'concentration' => '500 mg',
        'presentation_type' => 'Tableta',
    ]);

    $inventory = Inventory::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
    ]);

    $inventory->refresh();

    expect($inventory->product_category_id)->toBe($product->product_category_id)
        ->and($inventory->active_ingredient)->toBe(['Paracetamol'])
        ->and($inventory->concentration)->toBe('500 mg')
        ->and($inventory->presentation_type)->toBe('Tableta');
});

test('inventory refreshes pharmacy snapshot when product_id changes', function () {
    $branch = Branch::factory()->create();
    $productA = Product::factory()->medication()->create([
        'sku' => 'INV-SNAP-A',
        'active_ingredient' => ['Ibuprofeno'],
        'concentration' => '400 mg',
        'presentation_type' => 'Cápsula',
    ]);
    $productB = Product::factory()->medication()->create([
        'sku' => 'INV-SNAP-B',
        'active_ingredient' => ['Omeprazol'],
        'concentration' => '20 mg',
        'presentation_type' => 'Cápsula gastrorresistente',
    ]);

    $inventory = Inventory::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $productA->id,
    ]);

    expect($inventory->active_ingredient)->toBe(['Ibuprofeno']);

    $inventory->update(['product_id' => $productB->id]);
    $inventory->refresh();

    expect($inventory->active_ingredient)->toBe(['Omeprazol'])
        ->and($inventory->concentration)->toBe('20 mg')
        ->and($inventory->presentation_type)->toBe('Cápsula gastrorresistente');
});
