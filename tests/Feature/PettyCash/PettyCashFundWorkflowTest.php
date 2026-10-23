<?php

use App\Enums\PettyCashCurrency;
use App\Enums\PettyCashFundStatus;
use App\Models\Branch;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Services\Finance\PettyCashExpenseRegistrar;
use App\Services\Finance\PettyCashFundApprover;
use App\Services\Finance\PettyCashOpenFundGuard;
use App\Services\Hr\HrBcvRateResolver;

beforeEach(function (): void {
    $this->mock(HrBcvRateResolver::class, function ($mock): void {
        $mock->shouldReceive('resolveForDate')->andReturn(36.5);
    });
});

test('el administrador aprueba la solicitud y deja el saldo disponible', function (): void {
    $admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $fund = PettyCashFund::factory()->create([
        'status' => PettyCashFundStatus::PendingApproval,
        'amount_usd' => 100,
        'amount_ves' => 3650,
        'remaining_usd' => 100,
        'remaining_ves' => 3650,
    ]);

    $approved = app(PettyCashFundApprover::class)->approve(
        $fund,
        $admin,
        'petty-cash/payment-proofs/pago.jpg',
        'REF-12345',
    );

    expect($approved->status)->toBe(PettyCashFundStatus::Approved)
        ->and($approved->payment_reference)->toBe('REF-12345')
        ->and($approved->payment_proof_path)->toBe('petty-cash/payment-proofs/pago.jpg')
        ->and((float) $approved->remaining_usd)->toBe(100.0)
        ->and($approved->approved_by)->toBe($admin->id);
});

test('un gerente no puede aprobar la caja chica', function (): void {
    $manager = User::factory()->create(['roles' => ['GERENTE']]);
    $fund = PettyCashFund::factory()->create();

    app(PettyCashFundApprover::class)->approve(
        $fund,
        $manager,
        'petty-cash/payment-proofs/pago.jpg',
        'REF-12345',
    );
})->throws(RuntimeException::class);

test('el gasto en dólares descuenta el disponible hasta agotar la caja', function (): void {
    $manager = User::factory()->create(['roles' => ['GERENTE']]);
    $fund = PettyCashFund::factory()->approved()->create([
        'amount_usd' => 50,
        'amount_ves' => 1825,
        'remaining_usd' => 50,
        'remaining_ves' => 1825,
    ]);

    $registrar = app(PettyCashExpenseRegistrar::class);

    $registrar->register($fund, $manager, [
        'spent_at' => now(),
        'description' => 'Compra de insumos de oficina',
        'currency' => PettyCashCurrency::Usd,
        'amount' => 20,
        'receipt_path' => 'petty-cash/expense-receipts/ticket.jpg',
    ]);

    $fund->refresh();
    expect((float) $fund->remaining_usd)->toBe(30.0)
        ->and($fund->status)->toBe(PettyCashFundStatus::Approved);

    $registrar->register($fund, $manager, [
        'spent_at' => now(),
        'description' => 'Taxi y peaje',
        'currency' => PettyCashCurrency::Usd,
        'amount' => 30,
        'receipt_path' => 'petty-cash/expense-receipts/ticket-2.jpg',
    ]);

    $fund->refresh();
    expect((float) $fund->remaining_usd)->toBe(0.0)
        ->and((float) $fund->remaining_ves)->toBe(0.0)
        ->and($fund->status)->toBe(PettyCashFundStatus::Depleted)
        ->and($fund->expenses()->count())->toBe(2);
});

test('no se puede gastar más que el disponible', function (): void {
    $manager = User::factory()->create(['roles' => ['GERENTE']]);
    $fund = PettyCashFund::factory()->approved()->create([
        'remaining_usd' => 10,
        'remaining_ves' => 365,
    ]);

    app(PettyCashExpenseRegistrar::class)->register($fund, $manager, [
        'spent_at' => now(),
        'description' => 'Gasto excesivo',
        'currency' => PettyCashCurrency::Usd,
        'amount' => 10.02,
        'receipt_path' => 'petty-cash/expense-receipts/ticket.jpg',
    ]);
})->throws(InvalidArgumentException::class);

test('no se puede solicitar otra caja chica si hay una abierta en la sucursal', function (): void {
    $branch = Branch::factory()->create();
    PettyCashFund::factory()->approved()->create(['branch_id' => $branch->id]);

    app(PettyCashOpenFundGuard::class)->assertCanRequestForBranch($branch->id);
})->throws(RuntimeException::class);

test('tras agotar la caja se puede volver a solicitar', function (): void {
    $branch = Branch::factory()->create();
    PettyCashFund::factory()->depleted()->create(['branch_id' => $branch->id]);

    app(PettyCashOpenFundGuard::class)->assertCanRequestForBranch($branch->id);

    expect(true)->toBeTrue();
});
