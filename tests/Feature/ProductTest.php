<?php

use App\Models\Product;
use App\Models\Supplier;

test('product can be persisted with medication fields', function () {
    $product = Product::factory()->medication()->create([
        'name' => 'Paracetamol 500 mg',
        'sku' => 'MED-001',
    ]);

    $product->load('productCategory');

    expect($product->productCategory)->not->toBeNull()
        ->and($product->productCategory->is_medication)->toBeTrue()
        ->and($product->active_ingredient)->not->toBeNull()
        ->and($product->presentation_type)->not->toBeNull();
});

test('product can be persisted with food fields', function () {
    $product = Product::factory()->food()->create([
        'name' => 'Azúcar refinada 1 kg',
        'sku' => 'FOOD-001',
    ]);

    $product->load('productCategory');

    expect($product->productCategory)->not->toBeNull()
        ->and($product->productCategory->is_medication)->toBeFalse()
        ->and($product->ingredients)->not->toBeNull();
});

test('product can be persisted with medical equipment fields', function () {
    $product = Product::factory()->medicalEquipment()->create([
        'name' => 'Tensiómetro digital',
        'sku' => 'EQ-001',
    ]);

    $product->load('productCategory');

    expect($product->productCategory)->not->toBeNull()
        ->and($product->productCategory->is_medication)->toBeFalse()
        ->and($product->manufacturer)->not->toBeNull()
        ->and($product->model)->not->toBeNull();
});

test('product belongs to supplier when supplier_id is set', function () {
    $supplier = Supplier::factory()->create();

    $product = Product::factory()->for($supplier)->create([
        'sku' => 'REL-001',
    ]);

    $product->refresh();

    expect($product->supplier)->not->toBeNull()
        ->and($product->supplier->is($supplier))->toBeTrue();

    expect($supplier->products()->count())->toBe(1);
});
