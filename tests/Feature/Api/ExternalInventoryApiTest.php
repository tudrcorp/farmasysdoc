<?php

use App\Models\ApiClient;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\PartnerCompany;
use App\Models\Product;

test('external allies cannot query inventory without bearer token', function () {
    $response = $this->getJson(route('api.external.inventory.index', [
        'active_ingredient' => 'Paracetamol',
    ]));

    $response
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Token Bearer requerido.');
});

test('inventory query validates active_ingredient', function () {
    $plainToken = 'inventory-token-001';
    ApiClient::query()->create([
        'name' => 'Aliado Catálogo',
        'token_hash' => ApiClient::hashToken($plainToken),
        'is_active' => true,
    ]);

    PartnerCompany::query()->create([
        'code' => 'EXT-INV-VAL',
        'legal_name' => 'Compañía validación inventario API',
    ]);

    $this->withToken($plainToken)
        ->getJson(route('api.external.inventory.index'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['active_ingredient']);

    $this->withToken($plainToken)
        ->getJson(route('api.external.inventory.index', [
            'partner_company' => 'EXT-INV-VAL',
            'active_ingredient' => 'a',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['active_ingredient']);
});

test('external ally receives medications matching active ingredient with stock totals', function () {
    $plainToken = 'inventory-token-002';
    ApiClient::query()->create([
        'name' => 'Aliado Stock',
        'token_hash' => ApiClient::hashToken($plainToken),
        'is_active' => true,
    ]);

    PartnerCompany::query()->create([
        'code' => 'EXT-INV-STOCK',
        'legal_name' => 'Compañía stock inventario API',
    ]);

    $branch = Branch::factory()->create();

    $matchA = Product::factory()->medication()->create([
        'name' => 'Acetaminofén 500 mg',
        'sku' => 'MED-PARA-A',
        'active_ingredient' => ['Paracetamol 500 mg'],
        'is_active' => true,
    ]);

    $matchB = Product::factory()->medication()->create([
        'name' => 'Panadol Ultra',
        'sku' => 'MED-PARA-B',
        'active_ingredient' => ['Combinación: Paracetamol + cafeína'],
        'is_active' => true,
    ]);

    Product::factory()->medication()->create([
        'name' => 'Ibuprofeno 400 mg',
        'sku' => 'MED-IBU-1',
        'active_ingredient' => ['Ibuprofeno'],
        'is_active' => true,
    ]);

    Product::factory()->medication()->create([
        'name' => 'Paracetamol inactivo',
        'sku' => 'MED-PARA-OFF',
        'active_ingredient' => ['Paracetamol'],
        'is_active' => false,
    ]);

    Product::factory()->food()->create([
        'name' => 'Snack',
        'active_ingredient' => ['Paracetamol'],
    ]);

    Inventory::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $matchA->id,
        'quantity' => 100,
        'reserved_quantity' => 10,
        'allow_negative_stock' => false,
    ]);

    Inventory::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $matchB->id,
        'quantity' => 50,
        'reserved_quantity' => 0,
    ]);

    $response = $this->withToken($plainToken)->getJson(route('api.external.inventory.index', [
        'partner_company' => 'EXT-INV-STOCK',
        'active_ingredient' => 'paracetamol',
    ]));

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->sort()->values()->all();

    expect($ids)->toBe([$matchA->id, $matchB->id]);

    $rowA = collect($response->json('data'))->firstWhere('id', $matchA->id);
    expect((float) $rowA['total_available_quantity'])->toBe(90.0)
        ->and($rowA['sku'])->toBe('MED-PARA-A');

    $apiClient = ApiClient::query()->where('name', 'Aliado Stock')->firstOrFail();
    expect($apiClient->fresh()->last_used_at)->not->toBeNull();
});

test('inventory query returns empty list when no medication matches', function () {
    $plainToken = 'inventory-token-003';
    ApiClient::query()->create([
        'name' => 'Aliado Vacío',
        'token_hash' => ApiClient::hashToken($plainToken),
        'is_active' => true,
    ]);

    PartnerCompany::query()->create([
        'code' => 'EXT-INV-EMPTY',
        'legal_name' => 'Compañía lista vacía API',
    ]);

    Product::factory()->medication()->create([
        'active_ingredient' => ['Omeprazol'],
    ]);

    $response = $this->withToken($plainToken)->getJson(route('api.external.inventory.index', [
        'partner_company' => 'EXT-INV-EMPTY',
        'active_ingredient' => 'xyz-no-existe',
    ]));

    $response->assertOk()->assertJsonPath('data', []);
});
