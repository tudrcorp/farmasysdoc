<?php

use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('farmaadmin');
    $this->admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
});

test('an administrator sets the negotiation and payment days of a supplier', function (): void {
    $supplier = Supplier::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(EditSupplier::class, ['record' => $supplier->getKey()])
        ->fillForm([
            'payment_days' => 45,
            'negotiation_terms' => '5% de descuento por volumen',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $supplier->refresh();

    expect($supplier->payment_days)->toBe(45)
        ->and($supplier->negotiation_terms)->toBe('5% de descuento por volumen');
});

test('non administrators cannot change negotiation or payment days', function (): void {
    $supplier = Supplier::factory()->create(['payment_days' => 30, 'negotiation_terms' => 'Original']);
    $manager = User::factory()->create(['roles' => ['GERENCIA']]);

    $this->actingAs($manager);

    $supplier->fill(['payment_days' => 1, 'negotiation_terms' => 'Cambiado', 'notes' => 'Nota nueva'])->save();
    $supplier->refresh();

    expect($supplier->payment_days)->toBe(30)
        ->and($supplier->negotiation_terms)->toBe('Original')
        ->and($supplier->notes)->toBe('Nota nueva');
});

test('suppliers without payment days fall back to 30 days', function (): void {
    expect(Supplier::factory()->create(['payment_days' => null])->effectivePaymentDays())->toBe(30)
        ->and(Supplier::factory()->create(['payment_days' => 0])->paymentDaysLabel())->toBe('Contado');
});

test('the purchase due date is suggested from the supplier payment days', function (): void {
    $supplier = Supplier::factory()->create(['payment_days' => 15]);

    Livewire::actingAs($this->admin)
        ->test(CreatePurchase::class)
        ->set('data.supplier_invoice_date', '2026-11-01')
        ->set('data.supplier_id', (string) $supplier->id)
        ->assertSet('data.payment_due_date', '2026-11-16');
});
