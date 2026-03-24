<?php

use App\Filament\Resources\ApiClients\Pages\CreateApiClient;
use App\Filament\Resources\ApiClients\Pages\ViewApiClient;
use App\Models\ApiClient;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('farmaadmin');
});

test('create api client page loads form', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateApiClient::class)
        ->assertSuccessful();
});

test('api client can be created with generated token hash', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateApiClient::class)
        ->set('data', [
            'name' => 'Aliado QA',
            'is_active' => true,
            'allowed_ips' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $client = ApiClient::query()->where('name', 'Aliado QA')->firstOrFail();

    expect($client->token_hash)->toHaveLength(64)
        ->and($client->is_active)->toBeTrue();
});

test('view api client page loads infolist', function () {
    $user = User::factory()->create();
    $apiClient = ApiClient::factory()->create();

    Livewire::actingAs($user)
        ->test(ViewApiClient::class, ['record' => $apiClient->getRouteKey()])
        ->assertSuccessful();
});
