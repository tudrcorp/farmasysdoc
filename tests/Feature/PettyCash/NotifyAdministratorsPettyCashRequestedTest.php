<?php

use App\Models\PettyCashFund;
use App\Models\User;
use App\Support\Finance\NotifyAdministratorsPettyCashRequested;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Mockery\MockInterface;

test('no envía WhatsApp si UltraMsg está deshabilitado', function (): void {
    config(['services.ultramsg.enabled' => false]);

    $this->mock(UltramsgWhatsAppClient::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isEnabled')->andReturn(false);
        $mock->shouldReceive('sendImageMessage')->never();
        $mock->shouldReceive('sendTextMessage')->never();
    });

    $fund = PettyCashFund::factory()->create();
    $manager = User::factory()->create(['roles' => ['GERENTE']]);

    app(NotifyAdministratorsPettyCashRequested::class)->notify($fund, $manager);
});

test('envía a administradores un WhatsApp con el enlace de aprobación', function (): void {
    $admin = User::factory()->create([
        'roles' => ['ADMINISTRADOR'],
        'whatsapp_phone' => '04141234567',
    ]);
    $manager = User::factory()->create(['roles' => ['GERENTE'], 'name' => 'Gerente Prueba']);
    $fund = PettyCashFund::factory()->create([
        'requested_by' => $manager->id,
        'notes' => 'Caja para viáticos de sucursal',
    ]);

    $this->mock(UltramsgWhatsAppClient::class, function (MockInterface $mock) use ($fund): void {
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('resolveFarmadocLogoImage')->andReturn('data:image/png;base64,farmadoc');
        $mock->shouldReceive('sendImageMessage')
            ->once()
            ->withArgs(function (string $phone, string $image, string $caption) use ($fund): bool {
                return str_contains($phone, '584141234567')
                    && $image === 'data:image/png;base64,farmadoc'
                    && str_contains($caption, 'SOLICITUD DE CAJA CHICA')
                    && str_contains($caption, 'Caja para viáticos de sucursal')
                    && str_contains($caption, 'caja-chica/'.$fund->getKey().'/aprobar');
            })
            ->andReturn(true);
        $mock->shouldReceive('sendTextMessage')->never();
    });

    app(NotifyAdministratorsPettyCashRequested::class)->notify($fund, $manager);

    expect($admin)->not->toBeNull();
});
