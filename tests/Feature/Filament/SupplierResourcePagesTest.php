<?php

use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\ViewSupplier;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('farmaadmin');
});

test('create supplier page loads form with sections', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateSupplier::class)
        ->assertSuccessful();
});

test('view supplier page loads infolist', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();

    Livewire::actingAs($user)
        ->test(ViewSupplier::class, ['record' => $supplier->getRouteKey()])
        ->assertSuccessful();
});
