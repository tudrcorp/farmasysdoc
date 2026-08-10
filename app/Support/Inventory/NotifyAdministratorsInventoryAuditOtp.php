<?php

namespace App\Support\Inventory;

use App\Mail\InventoryAuditOtpMail;
use App\Models\User;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class NotifyAdministratorsInventoryAuditOtp
{
    public function __construct(
        private readonly UltramsgWhatsAppClient $ultramsgWhatsAppClient,
    ) {}

    /**
     * @param  list<string>  $changes
     */
    public function notify(
        User $manager,
        string $otpCode,
        ?string $productName = null,
        ?string $branchName = null,
        array $changes = [],
        int $ttlSeconds = 180,
    ): void {
        $admins = $this->resolveAdministrators();

        if ($admins === []) {
            Log::notice('OTP auditoría inventario: no hay administradores para notificar', [
                'manager_id' => $manager->getKey(),
            ]);

            return;
        }

        $managerLabel = (string) ($manager->name ?? $manager->email ?? 'gerente');
        $ttlMinutes = max(1, (int) ceil($ttlSeconds / 60));
        $caption = $this->buildWhatsAppCaption(
            otpCode: $otpCode,
            managerLabel: $managerLabel,
            productName: $productName,
            branchName: $branchName,
            changes: $changes,
            ttlMinutes: $ttlMinutes,
        );

        $whatsAppEnabled = $this->ultramsgWhatsAppClient->isEnabled();
        if (! $whatsAppEnabled) {
            Log::notice('UltraMsg deshabilitado: no se envía WhatsApp de OTP de auditoría', [
                'manager_id' => $manager->getKey(),
            ]);
        }

        $logoImage = $whatsAppEnabled
            ? $this->ultramsgWhatsAppClient->resolveFarmadocLogoImage()
            : null;

        foreach ($admins as $admin) {
            $this->sendEmail($admin, $otpCode, $managerLabel, $productName, $branchName, $changes, $ttlMinutes);

            if ($whatsAppEnabled) {
                $this->sendWhatsApp($admin, $caption, $otpCode, $logoImage);
            }
        }
    }

    /**
     * @return list<User>
     */
    private function resolveAdministrators(): array
    {
        return User::query()
            ->get(['id', 'name', 'email', 'roles', 'whatsapp_phone', 'delivery_mobile_phone'])
            ->filter(fn (User $user): bool => $user->isAdministrator())
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $changes
     */
    private function buildWhatsAppCaption(
        string $otpCode,
        string $managerLabel,
        ?string $productName,
        ?string $branchName,
        array $changes,
        int $ttlMinutes,
    ): string {
        $lines = [
            '*FARMADOC*',
            'OTP — Auditoría de inventario',
            '',
            'Revise el cambio solicitado antes de compartir la clave.',
            '',
        ];

        if (filled($productName)) {
            $lines[] = 'Producto: '.$productName;
        }

        if (filled($branchName)) {
            $lines[] = 'Sucursal: '.$branchName;
        }

        $lines[] = 'Solicitado por: '.$managerLabel;
        $lines[] = '';
        $lines[] = '*Cambios solicitados:*';

        if ($changes === []) {
            $lines[] = '• (sin detalle de cambios)';
        } else {
            foreach ($changes as $change) {
                $lines[] = '• '.$change;
            }
        }

        $lines[] = '';
        $lines[] = 'Clave (mantén pulsado para copiar):';
        $lines[] = '';
        $lines[] = $otpCode;
        $lines[] = '';
        $lines[] = 'Válido '.$ttlMinutes.' minutos · Un solo uso';

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $changes
     */
    private function sendEmail(
        User $admin,
        string $otpCode,
        string $managerLabel,
        ?string $productName,
        ?string $branchName,
        array $changes,
        int $ttlMinutes,
    ): void {
        if (! filled($admin->email)) {
            return;
        }

        try {
            Mail::to((string) $admin->email)->send(new InventoryAuditOtpMail(
                otpCode: $otpCode,
                managerName: $managerLabel,
                productName: $productName,
                branchName: $branchName,
                changes: $changes,
                ttlMinutes: $ttlMinutes,
            ));
        } catch (Throwable $exception) {
            Log::warning('OTP auditoría inventario: error al enviar email', [
                'admin_id' => $admin->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendWhatsApp(User $admin, string $caption, string $otpCode, ?string $logoImage): void
    {
        $phone = $this->normalizePhone(
            filled($admin->whatsapp_phone) ? $admin->whatsapp_phone : $admin->delivery_mobile_phone
        );

        if ($phone === null) {
            return;
        }

        try {
            $sentImage = false;

            if ($logoImage !== null) {
                $sentImage = $this->ultramsgWhatsAppClient->sendImageMessage($phone, $logoImage, $caption);
            }

            if (! $sentImage) {
                $this->ultramsgWhatsAppClient->sendTextMessage($phone, $caption);
            }

            // Mensaje aparte solo con la clave: en Android e iOS se copia con un toque prolongado.
            $this->ultramsgWhatsAppClient->sendTextMessage($phone, $otpCode);
        } catch (Throwable $exception) {
            Log::warning('OTP auditoría inventario: error al enviar WhatsApp', [
                'admin_id' => $admin->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $raw = trim((string) $phone);
        $raw = preg_replace('/\s+/', '', $raw) ?? '';
        $raw = preg_replace('/[^0-9+]/', '', $raw) ?? '';

        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, '00')) {
            $raw = '+'.substr($raw, 2);
        }

        $digitsOnly = preg_replace('/\D/', '', $raw) ?? '';

        if (! str_starts_with($raw, '+')) {
            if (str_starts_with($digitsOnly, '0') && strlen($digitsOnly) === 11) {
                $raw = '+58'.substr($digitsOnly, 1);
            } elseif (str_starts_with($digitsOnly, '58') && strlen($digitsOnly) >= 10) {
                $raw = '+'.$digitsOnly;
            } elseif (str_starts_with($digitsOnly, '4') && strlen($digitsOnly) === 10) {
                $raw = '+58'.$digitsOnly;
            } else {
                $raw = '+'.$digitsOnly;
            }
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return $raw;
    }
}
