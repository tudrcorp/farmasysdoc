<?php

use App\Enums\ConvenioType;
use App\Enums\OrderStatus;
use App\Models\ApiClient;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderService;
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

    $partner = PartnerCompany::query()->create([
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
            ],
            [
                'product_id' => $productB->id,
                'quantity' => 1,
                'unit_price' => 15000,
                'discount_amount' => 0,
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
        ->and($order->tax_total)->toBe('0.00')
        ->and($order->discount_total)->toBe('500.00')
        ->and($order->total)->toBe('34500.00')
        ->and($order->partner_company_id)->toBe($partner->id)
        ->and($order->serviceOrder)->toBeNull()
        ->and($order->created_by)->toBe('api:Aliado Integrador')
        ->and($apiClient->fresh()->last_used_at)->not->toBeNull();
});

test('an integracorp order opens a service order linked to that order and to the ally', function () {
    $plainToken = 'ally-token-service';
    ApiClient::query()->create([
        'name' => 'IntegraCorp',
        'token_hash' => ApiClient::hashToken($plainToken),
        'is_active' => true,
    ]);

    $partner = PartnerCompany::query()->create([
        'code' => 'ALDO-2026-0021',
        'legal_name' => 'IntegraCorp',
    ]);
    $client = Client::factory()->create();
    $branch = Branch::factory()->create();
    $product = Product::factory()->create([
        'name' => '20/20 SOL ACOND 120ML',
        'sku' => 'CSV-1',
    ]);

    $response = $this->withToken($plainToken)->postJson(route('api.external.orders.store'), [
        'partner_company' => 'ALDO-2026-0021',
        'order_number' => 'IC-2757-TEST',
        'client_id' => $client->id,
        'branch_id' => $branch->id,
        'status' => OrderStatus::Pending->value,
        'notes' => 'Pedido de medicamentos desde IntegraCorp.',
        'service_order' => [
            'patient_name' => 'CUESTA BERRIO HERMINIO',
            'patient_document' => '23782362',
            'patient_phone' => '0416-3034074',
            'patient_email' => 'paciente@example.com',
            'diagnosis' => 'Control',
            'external_reference' => 'REF-23711',
            'service_type' => 'medicamentos',
            'status' => 'en-proceso',
            'priority' => 'media',
        ],
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 0,
            'product_name_snapshot' => '20/20 SOL ACOND 120ML',
            'sku_snapshot' => 'CSV-1',
        ]],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.service_order.status', 'en-proceso');

    $order = Order::query()->where('order_number', 'IC-2757-TEST')->firstOrFail();
    $service = OrderService::query()->where('order_id', $order->getKey())->firstOrFail();

    expect($order->partner_company_id)->toBe($partner->id)
        ->and($service->partner_company_id)->toBe($partner->id)
        ->and($service->service_order_number)->toBe('ORD-00'.$service->getKey())
        ->and($service->patient_name)->toBe('CUESTA BERRIO HERMINIO')
        ->and($service->patient_phone)->toBe('04163034074')
        ->and($service->external_reference)->toBe('REF-23711')
        ->and($service->items[0]['name'])->toBe('20/20 SOL ACOND 120ML')
        ->and($service->items[0]['indicacion'])->toBe('Cantidad: 2')
        ->and($response->json('data.service_order.service_order_number'))->toBe($service->service_order_number);
});
