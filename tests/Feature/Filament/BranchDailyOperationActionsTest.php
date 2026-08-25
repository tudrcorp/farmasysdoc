<?php

use App\Filament\Resources\Branches\Pages\ListBranches;
use App\Models\Branch;
use App\Models\User;
use App\Services\Branches\BranchDailyOperationService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['services.ultramsg.enabled' => false]);
    Filament::setCurrentPanel('farmaadmin');
});

it('apertura la sucursal desde la accion de la tabla', function (): void {
    $admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $branch = Branch::factory()->create();

    Livewire::actingAs($admin)
        ->test(ListBranches::class)
        ->callAction(TestAction::make('openDailyOperation')->table($branch))
        ->assertNotified();

    expect(app(BranchDailyOperationService::class)->isOpen($branch->fresh()))->toBeTrue();
});

it('cierra la sucursal desde la accion de la tabla cuando no hay cajas abiertas', function (): void {
    $admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $branch = Branch::factory()->create();
    app(BranchDailyOperationService::class)->open($admin, $branch);

    Livewire::actingAs($admin)
        ->test(ListBranches::class)
        ->callAction(TestAction::make('closeDailyOperation')->table($branch->fresh()))
        ->assertNotified();

    expect(app(BranchDailyOperationService::class)->isOpen($branch->fresh()))->toBeFalse();
});
