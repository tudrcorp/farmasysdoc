<?php

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('farmaadmin');
});

test('create order page loads form with sections', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateOrder::class)
        ->assertSuccessful();
});

test('create purchase page loads form with sections', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreatePurchase::class)
        ->assertSuccessful();
});

test('create sale page loads form with sections', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateSale::class)
        ->assertSuccessful();
});
