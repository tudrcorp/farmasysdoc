<?php

use App\Enums\PurchaseStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;

test('purchase belongs to supplier and branch', function () {
    $supplier = Supplier::factory()->create();
    $branch = Branch::factory()->create();

    $purchase = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'purchase_number' => 'OC-TEST-001',
        'status' => PurchaseStatus::Ordered,
        'ordered_at' => now(),
    ]);

    expect($purchase->supplier->is($supplier))->toBeTrue()
        ->and($purchase->branch->is($branch))->toBeTrue()
        ->and($supplier->purchases)->toHaveCount(1)
        ->and($branch->purchases)->toHaveCount(1);
});

test('purchase has items linked to products', function () {
    $supplier = Supplier::factory()->create();
    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['sku' => 'COMP-SKU-1']);

    $purchase = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
    ]);

    $item = PurchaseItem::factory()->create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'quantity_ordered' => 24,
        'quantity_received' => 0,
        'unit_cost' => 5.5000,
        'line_subtotal' => 132.00,
        'tax_amount' => 0,
        'line_total' => 132.00,
        'product_name_snapshot' => $product->name,
        'sku_snapshot' => $product->sku,
    ]);

    expect($purchase->items)->toHaveCount(1)
        ->and($item->product->is($product))->toBeTrue()
        ->and($product->purchaseItems)->toHaveCount(1);
});
