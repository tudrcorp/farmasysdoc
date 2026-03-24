<?php

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('create product page loads form with sections', function () {
    Filament::setCurrentPanel('farmaadmin');

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateProduct::class)
        ->assertSuccessful();
});
