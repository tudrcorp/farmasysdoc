<?php

use App\Models\Branch;
use App\Models\PhysicalCashBox;
use App\Models\User;
use App\Services\Branches\BranchDailyOperationService;
use App\Support\Cash\PhysicalCashBoxBillingGate;

beforeEach(function (): void {
    config(['services.ultramsg.enabled' => false]);
});

it('bloquea la caja registradora si la sucursal no esta aperturada', function (): void {
    $branch = Branch::factory()->create();
    $cashier = User::factory()->create([
        'branch_id' => $branch->id,
        'roles' => ['CAJERO'],
    ]);

    PhysicalCashBox::factory()->open()->create([
        'user_id' => $cashier->id,
    ]);

    expect(PhysicalCashBoxBillingGate::userMayUseCashRegister($cashier))->toBeFalse()
        ->and(PhysicalCashBoxBillingGate::userMayOpenPhysicalCashBox($cashier))->toBeFalse()
        ->and(PhysicalCashBoxBillingGate::cashRegisterUnavailableMessage($cashier))
        ->toContain('sucursal aún no ha sido aperturada');
});

it('permite facturar al cajero cuando la sucursal y su caja fisica estan abiertas', function (): void {
    $branch = Branch::factory()->create();
    $cashier = User::factory()->create([
        'branch_id' => $branch->id,
        'roles' => ['CAJERO'],
    ]);
    $admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);

    app(BranchDailyOperationService::class)->open($admin, $branch);

    PhysicalCashBox::factory()->open()->create([
        'user_id' => $cashier->id,
    ]);

    expect(PhysicalCashBoxBillingGate::userMayUseCashRegister($cashier->fresh()))->toBeTrue()
        ->and(PhysicalCashBoxBillingGate::userMayOpenPhysicalCashBox($cashier->fresh()))->toBeTrue();
});

it('sigue exigiendo caja fisica abierta aunque la sucursal ya este aperturada', function (): void {
    $branch = Branch::factory()->create();
    $cashier = User::factory()->create([
        'branch_id' => $branch->id,
        'roles' => ['CAJERO'],
    ]);
    $admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);

    app(BranchDailyOperationService::class)->open($admin, $branch);

    expect(PhysicalCashBoxBillingGate::userMayUseCashRegister($cashier->fresh()))->toBeFalse()
        ->and(PhysicalCashBoxBillingGate::userMayOpenPhysicalCashBox($cashier->fresh()))->toBeTrue()
        ->and(PhysicalCashBoxBillingGate::cashRegisterUnavailableMessage($cashier->fresh()))
        ->toContain('caja física');
});
