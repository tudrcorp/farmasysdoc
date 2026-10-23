<?php

use App\Enums\EmployeeBankAccountType;
use App\Enums\PettyCashCurrency;
use App\Enums\PettyCashFundStatus;
use App\Enums\VenezuelanPagoMovilBank;
use App\Filament\Resources\PettyCashFunds\Pages\CreatePettyCashFund;
use App\Filament\Resources\PettyCashFunds\Pages\ListPettyCashFunds;
use App\Filament\Resources\PettyCashFunds\Pages\ViewPettyCashFund;
use App\Models\Branch;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Services\Hr\HrBcvRateResolver;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('farmaadmin');

    $this->mock(HrBcvRateResolver::class, function ($mock): void {
        $mock->shouldReceive('resolveForDate')->andReturn(36.5);
    });
});

test('el administrador ve el listado de caja chica', function (): void {
    $user = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $branch = Branch::factory()->create(['is_active' => true, 'name' => 'Sucursal Tab Prueba']);
    $funds = PettyCashFund::factory()->count(2)->create(['branch_id' => $branch->id]);

    Livewire::actingAs($user)
        ->test(ListPettyCashFunds::class)
        ->assertSuccessful()
        ->assertSee('Sucursal Tab Prueba')
        ->assertCanSeeTableRecords($funds)
        ->assertSee('Egresos del mes')
        ->assertSee('Gastos registrados');
});

test('el administrador puede crear una solicitud de caja chica', function (): void {
    $user = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $branch = Branch::factory()->create(['is_active' => true]);

    Livewire::actingAs($user)
        ->test(CreatePettyCashFund::class)
        ->fillForm([
            'branch_id' => $branch->id,
            'requested_at' => now()->toDateString(),
            'requested_currency' => PettyCashCurrency::Usd->value,
            'requested_amount' => 80,
            'notes' => 'Fondo para gastos menores de sucursal',
            'bank_code' => VenezuelanPagoMovilBank::BancoDeVenezuela->value,
            'bank_account_type' => EmployeeBankAccountType::Checking->value,
            'bank_account_holder' => 'Gerente Sucursal',
            'holder_national_id' => 'V-12345678',
            'holder_phone' => '0412-1234567',
            'bank_account_number' => '0102-1234-56-1234567890',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $fund = PettyCashFund::query()->first();

    expect($fund)->not->toBeNull()
        ->and($fund->status)->toBe(PettyCashFundStatus::PendingApproval)
        ->and($fund->requested_by)->toBe($user->id)
        ->and((float) $fund->amount_usd)->toBe(80.0)
        ->and($fund->bank_account_number)->toBe('01021234561234567890')
        ->and($fund->holder_national_id)->toBe('V-12345678')
        ->and($fund->holder_phone)->toBe('0412-1234567');
});

test('la ficha de caja chica carga el infolist', function (): void {
    $user = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $fund = PettyCashFund::factory()->approved()->create();

    Livewire::actingAs($user)
        ->test(ViewPettyCashFund::class, ['record' => $fund->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Cuenta bancaria de destino');
});
