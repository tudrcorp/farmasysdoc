<?php

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;

test('sale belongs to branch and optionally to client', function () {
    $branch = Branch::factory()->create();
    $client = Client::factory()->create();

    $sale = Sale::factory()->create([
        'branch_id' => $branch->id,
        'client_id' => $client->id,
        'sale_number' => 'VTA-TEST-001',
        'status' => SaleStatus::Completed,
    ]);

    expect($sale->branch->is($branch))->toBeTrue()
        ->and($sale->client?->is($client))->toBeTrue()
        ->and($branch->sales)->toHaveCount(1)
        ->and($client->sales)->toHaveCount(1);
});

test('sale has many items linked to products', function () {
    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['sku' => 'SALE-SKU-1']);

    $sale = Sale::factory()->create(['branch_id' => $branch->id]);

    $item = SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 15.00,
        'discount_amount' => 0,
        'line_subtotal' => 30.00,
        'tax_amount' => 0,
        'line_total' => 30.00,
        'product_name_snapshot' => $product->name,
        'sku_snapshot' => $product->sku,
    ]);

    expect($sale->items)->toHaveCount(1)
        ->and($item->product->is($product))->toBeTrue()
        ->and($product->saleItems)->toHaveCount(1);
});

test('sale item can reference inventory of the branch', function () {
    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['sku' => 'SALE-SKU-2']);
    $inventory = Inventory::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
    ]);

    $sale = Sale::factory()->create(['branch_id' => $branch->id]);

    $item = SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'inventory_id' => $inventory->id,
        'quantity' => 1,
        'unit_price' => 10.00,
        'discount_amount' => 0,
        'line_subtotal' => 10.00,
        'tax_amount' => 0,
        'line_total' => 10.00,
        'product_name_snapshot' => $product->name,
        'sku_snapshot' => $product->sku,
    ]);

    expect($item->inventory?->is($inventory))->toBeTrue()
        ->and($inventory->saleItems)->toHaveCount(1);
});
