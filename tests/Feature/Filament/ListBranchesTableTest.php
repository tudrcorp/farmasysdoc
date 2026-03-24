<?php

use App\Filament\Resources\Branches\Pages\ListBranches;
use App\Models\Branch;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('list branches page loads table with records', function (): void {
    Filament::setCurrentPanel('farmaadmin');

    $user = User::factory()->create();
    $branches = Branch::factory()->count(3)->create();

    Livewire::actingAs($user)
        ->test(ListBranches::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($branches);
});
