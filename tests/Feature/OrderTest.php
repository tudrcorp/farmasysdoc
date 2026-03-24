<?php

use App\Enums\ConvenioType;
use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

test('order belongs to client and branch with convenio fields', function () {
    $client = Client::factory()->create();
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'branch_id' => $branch->id,
        'order_number' => 'PED-TEST-001',
        'status' => OrderStatus::Confirmed,
        'convenio_type' => ConvenioType::Eps,
        'convenio_partner_name' => 'EPS Sanitas',
        'convenio_reference' => 'AUT-12345678',
    ]);

    expect($order->client->is($client))->toBeTrue()
        ->and($order->branch?->is($branch))->toBeTrue()
        ->and($order->convenio_type)->toBe(ConvenioType::Eps)
        ->and($client->orders)->toHaveCount(1)
        ->and($branch->orders)->toHaveCount(1);
});

test('order has items and can link inventory for dispatch', function () {
    $branch = Branch::factory()->create();
    $client = Client::factory()->create();
    $product = Product::factory()->create(['sku' => 'ORD-SKU-1']);
    $inventory = Inventory::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
    ]);

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'branch_id' => $branch->id,
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'inventory_id' => $inventory->id,
        'quantity' => 3,
        'unit_price' => 20.00,
        'discount_amount' => 0,
        'tax_rate' => 19,
        'line_subtotal' => 60.00,
        'tax_amount' => 11.40,
        'line_total' => 71.40,
        'product_name_snapshot' => $product->name,
        'sku_snapshot' => $product->sku,
    ]);

    expect($order->items)->toHaveCount(1)
        ->and($item->product->is($product))->toBeTrue()
        ->and($item->inventory?->is($inventory))->toBeTrue()
        ->and($product->orderItems)->toHaveCount(1)
        ->and($inventory->orderItems)->toHaveCount(1);
});

test('insurance convenio factory state sets partner and reference', function () {
    $order = Order::factory()->withInsuranceConvenio()->create();

    expect($order->convenio_type)->toBe(ConvenioType::PrivateInsurance)
        ->and($order->convenio_partner_name)->not->toBeNull()
        ->and($order->convenio_reference)->not->toBeNull();
});
