<?php

use App\Filament\Resources\Branches\Pages\CreateBranch;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('create branch page loads form with sections', function () {
    Filament::setCurrentPanel('farmaadmin');

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateBranch::class)
        ->assertSuccessful();
});
