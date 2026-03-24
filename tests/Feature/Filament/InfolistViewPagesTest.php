<?php

use App\Filament\Resources\Branches\Pages\ViewBranch;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Inventories\Pages\ViewInventory;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('farmaadmin');
});

test('view branch page loads infolist', function () {
    $user = User::factory()->create();
    $branch = Branch::factory()->create();

    Livewire::actingAs($user)
        ->test(ViewBranch::class, ['record' => $branch->getRouteKey()])
        ->assertSuccessful();
});

test('view client page loads infolist', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    Livewire::actingAs($user)
        ->test(ViewClient::class, ['record' => $client->getRouteKey()])
        ->assertSuccessful();
});

test('view product page loads infolist', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    Livewire::actingAs($user)
        ->test(ViewProduct::class, ['record' => $product->getRouteKey()])
        ->assertSuccessful();
});

test('view inventory page loads infolist', function () {
    $user = User::factory()->create();
    $inventory = Inventory::factory()->create();

    Livewire::actingAs($user)
        ->test(ViewInventory::class, ['record' => $inventory->getRouteKey()])
        ->assertSuccessful();
});
