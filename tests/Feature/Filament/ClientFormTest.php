<?php

use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('create client page loads form with sections', function () {
    Filament::setCurrentPanel('farmaadmin');

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateClient::class)
        ->assertSuccessful();
});
