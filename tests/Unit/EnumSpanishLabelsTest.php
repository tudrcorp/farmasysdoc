<?php

use App\Enums\ConvenioType;
use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;

test('los enums exponen etiquetas en español y opciones coherentes', function () {
    expect(SaleStatus::Draft->label())->toBe('Borrador')
        ->and(count(SaleStatus::options()))->toBe(count(SaleStatus::cases()));

    expect(OrderStatus::Pending->label())->toBe('Pendiente')
        ->and(count(OrderStatus::options()))->toBe(count(OrderStatus::cases()));

    expect(PurchaseStatus::Ordered->label())->toBe('Pedido al proveedor')
        ->and(count(PurchaseStatus::options()))->toBe(count(PurchaseStatus::cases()));

    expect(ProductType::Medication->label())->toBe('Medicamento')
        ->and(count(ProductType::options()))->toBe(count(ProductType::cases()));

    expect(ConvenioType::Eps->label())->toBe('EPS')
        ->and(count(ConvenioType::options()))->toBe(count(ConvenioType::cases()));

    expect(InventoryMovementType::Purchase->label())->toBe('Compra')
        ->and(count(InventoryMovementType::options()))->toBe(count(InventoryMovementType::cases()));
});

test('tryLabel resuelve instancias y cadenas', function () {
    expect(InventoryMovementType::tryLabel(InventoryMovementType::Sale))->toBe('Venta')
        ->and(InventoryMovementType::tryLabel('venta'))->toBe('Venta')
        ->and(InventoryMovementType::tryLabel('unknown'))->toBe('unknown');
});
