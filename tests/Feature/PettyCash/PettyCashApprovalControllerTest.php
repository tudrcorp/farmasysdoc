<?php

use App\Enums\PettyCashFundStatus;
use App\Models\PettyCashFund;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Filament::setCurrentPanel('farmaadmin');
});

test('el enlace firmado muestra la solicitud al administrador', function (): void {
    $admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $fund = PettyCashFund::factory()->create([
        'notes' => 'Fondo para caja de sucursal',
    ]);

    $url = URL::temporarySignedRoute(
        'petty-cash.approval.show',
        now()->addDay(),
        ['fund' => $fund->getKey()],
    );

    $this->actingAs($admin)
        ->get($url)
        ->assertSuccessful()
        ->assertSee('Solicitud de caja chica')
        ->assertSee('Fondo para caja de sucursal')
        ->assertSee('Aprobar y guardar');
});

test('sin firma el enlace de caja chica es rechazado', function (): void {
    $admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $fund = PettyCashFund::factory()->create();

    $this->actingAs($admin)
        ->get(route('petty-cash.approval.show', $fund))
        ->assertForbidden();
});

test('un gerente no puede abrir el enlace de aprobación', function (): void {
    $manager = User::factory()->create(['roles' => ['GERENTE']]);
    $fund = PettyCashFund::factory()->create();

    $url = URL::temporarySignedRoute(
        'petty-cash.approval.show',
        now()->addDay(),
        ['fund' => $fund->getKey()],
    );

    $this->actingAs($manager)
        ->get($url)
        ->assertForbidden();
});

test('el administrador aprueba desde el enlace y carga comprobante y referencia', function (): void {
    Storage::fake('public');

    $admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $fund = PettyCashFund::factory()->create();

    $url = URL::temporarySignedRoute(
        'petty-cash.approval.approve',
        now()->addDay(),
        ['fund' => $fund->getKey()],
    );

    $this->actingAs($admin)
        ->post($url, [
            'payment_reference' => 'TRX-7788',
            'payment_proof' => UploadedFile::fake()->image('comprobante.jpg', 400, 400),
        ])
        ->assertRedirect(route('petty-cash.approval.complete', $fund));

    $fund->refresh();

    expect($fund->status)->toBe(PettyCashFundStatus::Approved)
        ->and($fund->payment_reference)->toBe('TRX-7788')
        ->and($fund->approved_by)->toBe($admin->id)
        ->and($fund->payment_proof_path)->not->toBeNull();

    Storage::disk('public')->assertExists((string) $fund->payment_proof_path);
});

test('el administrador rechaza desde el enlace', function (): void {
    $admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $fund = PettyCashFund::factory()->create();

    $url = URL::temporarySignedRoute(
        'petty-cash.approval.reject',
        now()->addDay(),
        ['fund' => $fund->getKey()],
    );

    $this->actingAs($admin)
        ->post($url, [
            'rejection_reason' => 'Monto no justificado',
        ])
        ->assertRedirect(route('petty-cash.approval.complete', $fund));

    $fund->refresh();

    expect($fund->status)->toBe(PettyCashFundStatus::Rejected)
        ->and($fund->rejection_reason)->toBe('Monto no justificado');
});

test('la pantalla listo funciona tras rechazar aunque no haya flash de sesión', function (): void {
    $admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $fund = PettyCashFund::factory()->create([
        'status' => PettyCashFundStatus::Rejected,
        'rejection_reason' => 'Reposición no procede',
    ]);

    $this->actingAs($admin)
        ->get(route('petty-cash.approval.complete', ['fund' => $fund->getKey()]))
        ->assertSuccessful()
        ->assertSee('rechazada', false);
});
