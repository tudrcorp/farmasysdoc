<?php

namespace App\Console\Commands;

use App\Models\PhysicalCashBox;
use App\Models\User;
use App\Support\Cash\NotifyOnPhysicalCashBoxOpen;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Illuminate\Console\Command;

final class TestPhysicalCashBoxOpenWhatsAppCommand extends Command
{
    protected $signature = 'whatsapp:test-cash-box-open
                            {--phone= : Enviar solo a este número (ej. +584121234567)}
                            {--cashier= : ID del cajero a simular (default: último cajero con caja)}
                            {--text-only : Omitir imagen y enviar solo texto}';

    protected $description = 'Prueba real del WhatsApp de apertura de caja física (UltraMsg)';

    public function handle(
        UltramsgWhatsAppClient $ultramsgWhatsAppClient,
        NotifyOnPhysicalCashBoxOpen $notifyOnPhysicalCashBoxOpen,
    ): int {
        if (! $ultramsgWhatsAppClient->isEnabled()) {
            $this->error('UltraMsg no está habilitado. Revise ULTRAMSG_ENABLED, ULTRAMSG_BASE_URL y ULTRAMSG_TOKEN en .env');

            return self::FAILURE;
        }

        $phone = trim((string) $this->option('phone'));
        $textOnly = (bool) $this->option('text-only');

        $cashier = $this->resolveCashier();
        if (! $cashier instanceof User) {
            $this->error('No se encontró un cajero para simular la apertura.');

            return self::FAILURE;
        }

        $box = PhysicalCashBox::query()
            ->where('user_id', $cashier->getKey())
            ->first();

        if (! $box instanceof PhysicalCashBox) {
            $this->error('El cajero no tiene registro de caja física.');

            return self::FAILURE;
        }

        $box->loadMissing('user.branch');
        $cashier->loadMissing('branch');

        $openingUsd = (float) $box->amount_usd;
        $openingVes = (float) $box->amount_ves;
        if (! $box->is_open) {
            $openingUsd = 100.0;
            $openingVes = 5000.0;
        }

        if ($box->opened_at === null) {
            $box->opened_at = now();
        }

        $bannerImage = $textOnly ? null : $ultramsgWhatsAppClient->resolvePhysicalCashBoxBannerImage();
        $caption = $this->resolveCaption($notifyOnPhysicalCashBoxOpen, $cashier, $box, $openingUsd, $openingVes);

        $this->line('Cajero: '.($cashier->name ?? $cashier->email ?? '#'.$cashier->getKey()));
        $this->line('Sucursal: '.($cashier->branch?->name ?? 'Sin sucursal'));
        $this->line('Banner: '.($bannerImage === null ? 'sin imagen' : (str_starts_with($bannerImage, 'http') ? 'URL' : 'base64 ('.strlen($bannerImage).' chars)')));

        if ($phone !== '') {
            $this->line('Destino de prueba: '.$phone);
            $sent = $bannerImage !== null
                ? $ultramsgWhatsAppClient->sendImageMessage($phone, $bannerImage, $caption)
                : false;

            if (! $sent) {
                $sent = $ultramsgWhatsAppClient->sendTextMessage($phone, $caption);
            }

            if (! $sent) {
                $this->error('UltraMsg no confirmó el envío. Revise storage/logs/laravel.log');

                return self::FAILURE;
            }

            $this->info('Mensaje de prueba enviado a '.$phone.'.');

            return self::SUCCESS;
        }

        $this->line('Modo producción: notificando gerentes y administradores del cajero...');
        $notifyOnPhysicalCashBoxOpen->notify($cashier, $box, $openingUsd, $openingVes);
        $this->info('Proceso completado. Si no llegó el WhatsApp, revise teléfonos whatsapp_phone y storage/logs/laravel.log');

        return self::SUCCESS;
    }

    private function resolveCashier(): ?User
    {
        $cashierId = $this->option('cashier');
        if (filled($cashierId)) {
            $user = User::query()->find((int) $cashierId);

            return $user instanceof User ? $user : null;
        }

        $openBox = PhysicalCashBox::query()
            ->where('is_open', true)
            ->latest('opened_at')
            ->first();

        if ($openBox instanceof PhysicalCashBox) {
            return User::query()->find($openBox->user_id);
        }

        return User::query()
            ->whereNotNull('branch_id')
            ->orderByDesc('id')
            ->get()
            ->first(fn (User $user): bool => $user->isCashier());
    }

    private function resolveCaption(
        NotifyOnPhysicalCashBoxOpen $notifyOnPhysicalCashBoxOpen,
        User $cashier,
        PhysicalCashBox $box,
        float $openingUsd,
        float $openingVes,
    ): string {
        $reflection = new \ReflectionClass($notifyOnPhysicalCashBoxOpen);
        $method = $reflection->getMethod('buildCaption');
        $method->setAccessible(true);

        return (string) $method->invoke(
            $notifyOnPhysicalCashBoxOpen,
            $cashier,
            $box,
            $openingUsd,
            $openingVes,
        );
    }
}
