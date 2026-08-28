<?php

use App\Filament\Pages\ManageFiscalCompanySettings;
use App\Models\FiscalCompanySetting;
use App\Models\User;
use App\Support\Fiscal\CompanyFiscalAddress;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('farmaadmin');
    CompanyFiscalAddress::forgetCache();
});

test('administrator can open the fiscal company settings page', function (): void {
    $user = User::factory()->create(['roles' => ['ADMINISTRADOR']]);

    Livewire::actingAs($user)
        ->test(ManageFiscalCompanySettings::class)
        ->assertSuccessful()
        ->assertSee('Dirección de la empresa principal');
});

test('administrator can save the main company fiscal address', function (): void {
    $user = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $address = 'AV PRINCIPAL CASA NRO 10 URB CENTRO BARINAS';

    Livewire::actingAs($user)
        ->test(ManageFiscalCompanySettings::class)
        ->set('address', $address)
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotified();

    $setting = FiscalCompanySetting::query()->whereKey(1)->first();

    expect($setting)->not->toBeNull()
        ->and($setting->address)->toBe($address)
        ->and(CompanyFiscalAddress::line())->toBe($address);
});
