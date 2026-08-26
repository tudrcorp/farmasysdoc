<?php

namespace App\Console\Commands;

use App\Models\PhysicalCashBox;
use App\Models\User;
use App\Services\Sales\PhysicalCashBoxShiftPaymentTotalsPdfGenerator;
use App\Services\Sales\PhysicalCashBoxShiftReportBuilder;
use App\Support\Cash\NotifyAdministratorsOnPhysicalCashBoxClose;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Illuminate\Console\Command;

final class TestPhysicalCashBoxCloseWhatsAppCommand extends Command
{
    protected $signature = 'whatsapp:test-cash-box-close
                            {--phone= : Enviar solo a este número (ej. +584121234567)}
                            {--cashier= : ID del cajero a simular (default: último cajero con caja)}
                            {--text-only : Omitir imagen y enviar solo texto}
                            {--pdf-only : Enviar solo el PDF de totales por tipo de pago}';

    protected $description = 'Prueba real del WhatsApp de cierre de caja física (UltraMsg)';

    public function handle(
        UltramsgWhatsAppClient $ultramsgWhatsAppClient,
        PhysicalCashBoxShiftReportBuilder $shiftReportBuilder,
        PhysicalCashBoxShiftPaymentTotalsPdfGenerator $paymentTotalsPdfGenerator,
        NotifyAdministratorsOnPhysicalCashBoxClose $notifyAdministratorsOnPhysicalCashBoxClose,
    ): int {
        if (! $ultramsgWhatsAppClient->isEnabled()) {
            $this->error('UltraMsg no está habilitado. Revise ULTRAMSG_ENABLED, ULTRAMSG_BASE_URL y ULTRAMSG_TOKEN en .env');

            return self::FAILURE;
        }

        $phone = trim((string) $this->option('phone'));
        $textOnly = (bool) $this->option('text-only');
        $pdfOnly = (bool) $this->option('pdf-only');

        $cashier = $this->resolveCashier();
        if (! $cashier instanceof User) {
            $this->error('No se encontró un cajero para simular el cierre.');

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

        $openedAt = $box->opened_at ?? now()->subHours(8);
        $closedAt = now();

        if ($phone === '') {
            $this->line('Modo producción: notificando administradores del cierre simulado...');
            $notifyAdministratorsOnPhysicalCashBoxClose->notify($cashier, $box, $openedAt, $closedAt);
            $this->info('Proceso completado. Si no llegó el WhatsApp, revise teléfonos whatsapp_phone y storage/logs/laravel.log');

            return self::SUCCESS;
        }

        $report = $shiftReportBuilder->build($cashier, $box, $openedAt, $closedAt);
        $caption = $this->resolveCaption($notifyAdministratorsOnPhysicalCashBoxClose, $report);
        $bannerImage = $textOnly || $pdfOnly ? null : $ultramsgWhatsAppClient->resolvePhysicalCashBoxBannerImage();
        $pdfBytes = $paymentTotalsPdfGenerator->generate($report);
        $pdfDocument = base64_encode($pdfBytes);
        $pdfFilename = 'totales-pago-cierre-caja-'.$closedAt->timezone((string) config('app.timezone'))->format('Y-m-d-His').'.pdf';
        $pdfCaption = $this->resolveMediaCaption($notifyAdministratorsOnPhysicalCashBoxClose, $report);

        $this->line('Cajero: '.($cashier->name ?? $cashier->email ?? '#'.$cashier->getKey()));
        $this->line('Sucursal: '.($cashier->branch?->name ?? 'Sin sucursal'));
        $this->line('Destino de prueba: '.$phone);
        $this->line('Banner: '.($bannerImage === null ? 'sin imagen' : (str_starts_with($bannerImage, 'http') ? 'URL' : 'base64 ('.strlen($bannerImage).' chars)')));
        $this->line('PDF: '.strlen($pdfBytes).' bytes');
        $this->line('Arqueo (texto): '.mb_strlen($caption).' caracteres');

        if (! $pdfOnly) {
            $sentText = $ultramsgWhatsAppClient->sendTextMessage($phone, $caption);
            if (! $sentText) {
                $this->error('UltraMsg no confirmó el envío del arqueo de texto.');

                return self::FAILURE;
            }

            $this->info('Arqueo de texto enviado.');

            if ($bannerImage !== null && ! $textOnly) {
                $mediaCaption = $this->resolveMediaCaption($notifyAdministratorsOnPhysicalCashBoxClose, $report);
                $sentImage = $ultramsgWhatsAppClient->sendImageMessage($phone, $bannerImage, $mediaCaption);
                if (! $sentImage) {
                    $this->warn('No se pudo enviar la imagen de encabezado. El arqueo de texto sí salió.');
                }
            }
        }

        $sentDocument = $ultramsgWhatsAppClient->sendDocumentMessage(
            $phone,
            $pdfDocument,
            $pdfFilename,
            $pdfCaption,
        );

        if (! $sentDocument) {
            $this->error('UltraMsg no confirmó el envío del PDF. Revise storage/logs/laravel.log');

            return self::FAILURE;
        }

        $this->info('PDF de totales por tipo de pago enviado a '.$phone.'.');

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

    /**
     * @param  array<string, mixed>  $report
     */
    private function resolveCaption(NotifyAdministratorsOnPhysicalCashBoxClose $notifier, array $report): string
    {
        return $this->invokePrivateNotifierMethod($notifier, 'buildCaption', $report);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function resolveMediaCaption(NotifyAdministratorsOnPhysicalCashBoxClose $notifier, array $report): string
    {
        return $this->invokePrivateNotifierMethod($notifier, 'buildMediaCaption', $report);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function invokePrivateNotifierMethod(
        NotifyAdministratorsOnPhysicalCashBoxClose $notifier,
        string $methodName,
        array $report,
    ): string {
        $reflection = new \ReflectionClass($notifier);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return (string) $method->invoke($notifier, $report);
    }
}
