<?php

use App\Enums\ConvenioType;
use App\Enums\OrderStatus;
use App\Models\ApiClient;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerCompany;
use App\Models\Product;

test('external allies cannot create orders without bearer token', function () {
    $response = $this->postJson(route('api.external.orders.store'), []);

    $response
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Token Bearer requerido.');
});

test('external ally can create order and items with valid bearer token', function () {
    $plainToken = 'ally-token-001';
    $apiClient = ApiClient::query()->create([
        'name' => 'Aliado Integrador',
        'token_hash' => ApiClient::hashToken($plainToken),
        'is_active' => true,
    ]);

    PartnerCompany::query()->create([
        'code' => 'EXT-ORD-001',
        'legal_name' => 'Compañía pedidos API',
    ]);

    $client = Client::factory()->create();
    $branch = Branch::factory()->create();
    $productA = Product::factory()->create([
        'name' => 'Paracetamol 500 mg',
        'sku' => 'MED-500-A',
    ]);
    $productB = Product::factory()->create([
        'name' => 'Ibuprofeno 400 mg',
        'sku' => 'MED-400-B',
    ]);

    $response = $this->withToken($plainToken)->postJson(route('api.external.orders.store'), [
        'partner_company' => 'EXT-ORD-001',
        'client_id' => $client->id,
        'branch_id' => $branch->id,
        'status' => OrderStatus::Pending->value,
        'convenio_type' => ConvenioType::Particular->value,
        'delivery_recipient_name' => 'Juan Perez',
        'delivery_phone' => '3001234567',
        'delivery_address' => 'Calle 123 # 45-67',
        'items' => [
            [
                'product_id' => $productA->id,
                'quantity' => 2,
                'unit_price' => 10000,
                'discount_amount' => 500,
                'tax_rate' => 19,
            ],
            [
                'product_id' => $productB->id,
                'quantity' => 1,
                'unit_price' => 15000,
                'discount_amount' => 0,
                'tax_rate' => 5,
            ],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Pedido creado correctamente.')
        ->assertJsonPath('data.items_count', 2);

    expect(Order::query()->count())->toBe(1)
        ->and(OrderItem::query()->count())->toBe(2);

    $order = Order::query()->firstOrFail();

    expect($order->subtotal)->toBe('34500.00')
        ->and($order->tax_total)->toBe('4455.00')
        ->and($order->discount_total)->toBe('500.00')
        ->and($order->total)->toBe('38955.00')
        ->and($order->created_by)->toBe('api:Aliado Integrador')
        ->and($apiClient->fresh()->last_used_at)->not->toBeNull();
});
