<?php

use App\Filament\Resources\Inventories\Pages\CreateInventory;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('create inventory page loads form with sections', function () {
    Filament::setCurrentPanel('farmaadmin');

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateInventory::class)
        ->assertSuccessful();
});
