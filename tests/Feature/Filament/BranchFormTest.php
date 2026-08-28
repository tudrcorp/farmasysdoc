<?php

use App\Filament\Resources\Branches\Pages\CreateBranch;
use App\Filament\Resources\Branches\Pages\EditBranch;
use App\Models\Branch;
use App\Models\Country;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('create branch page loads form with sections', function () {
    Filament::setCurrentPanel('farmaadmin');

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateBranch::class)
        ->assertSuccessful();
});

test('creating a branch assigns automatic SUC-{id} code', function () {
    Filament::setCurrentPanel('farmaadmin');

    Country::query()->create([
        'name' => 'Colombia',
        'code' => 'CO',
    ]);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateBranch::class)
        ->set('data', [
            'name' => 'Sucursal test',
            'country' => 'Colombia',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $branch = Branch::query()->where('name', 'Sucursal test')->first();

    expect($branch)->not->toBeNull()
        ->and($branch->code)->toBe('SUC-'.$branch->id);
});

test('administrator can save a fiscal address on a branch', function (): void {
    Filament::setCurrentPanel('farmaadmin');

    $user = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $branch = Branch::factory()->create(['address' => null]);
    $address = 'Av. Los Llanos, local 12, Barinas';

    Livewire::actingAs($user)
        ->test(EditBranch::class, ['record' => $branch->getRouteKey()])
        ->fillForm([
            'address' => $address,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($branch->refresh()->fiscalAddress())->toBe($address);
});
