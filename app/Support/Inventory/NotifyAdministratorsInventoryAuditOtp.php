<?php

namespace App\Support\Inventory;

use App\Mail\InventoryAuditOtpMail;
use App\Models\User;
use App\Support\Branches\BranchDailyOperationRecipients;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class NotifyAdministratorsInventoryAuditOtp
{
    public function __construct(
        private readonly UltramsgWhatsAppClient $ultramsgWhatsAppClient,
        private readonly BranchDailyOperationRecipients $branchRecipients,
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
        ?int $branchId = null,
    ): void {
        $recipients = $this->resolveRecipients($manager, $branchId);

        if ($recipients === []) {
            Log::notice('OTP auditoría inventario: no hay destinatarios para notificar', [
                'manager_id' => $manager->getKey(),
                'branch_id' => $branchId,
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

        foreach ($recipients as $recipient) {
            $this->sendEmail($recipient, $otpCode, $managerLabel, $productName, $branchName, $changes, $ttlMinutes);

            if ($whatsAppEnabled) {
                $this->sendWhatsApp($recipient, $caption, $otpCode, $logoImage);
            }
        }
    }

    /**
     * Administradores, gerentes de la sucursal y quien solicita el OTP.
     *
     * @return list<User>
     */
    private function resolveRecipients(User $manager, ?int $branchId): array
    {
        $users = User::query()
            ->with('managedBranches:id')
            ->get(['id', 'name', 'email', 'roles', 'branch_id', 'whatsapp_phone', 'delivery_mobile_phone']);

        $recipients = $users
            ->filter(function (User $user) use ($branchId): bool {
                if ($user->isAdministrator()) {
                    return true;
                }

                if ($branchId === null || $branchId <= 0 || ! $user->isManager()) {
                    return false;
                }

                return $this->branchRecipients->shouldNotifyUser($user, $branchId);
            });

        $actor = $users->first(
            fn (User $user): bool => (int) $user->getKey() === (int) $manager->getKey(),
        );

        if (! $actor instanceof User) {
            $actor = User::query()
                ->with('managedBranches:id')
                ->whereKey($manager->getKey())
                ->first(['id', 'name', 'email', 'roles', 'branch_id', 'whatsapp_phone', 'delivery_mobile_phone']);
        }

        if ($actor instanceof User) {
            $recipients = $recipients
                ->reject(fn (User $user): bool => (int) $user->getKey() === (int) $actor->getKey())
                ->prepend($actor);
        }

        return $recipients->unique('id')->values()->all();
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
            'Revise el cambio solicitado. Use o entregue la clave solo si autoriza.',
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
        User $recipient,
        string $otpCode,
        string $managerLabel,
        ?string $productName,
        ?string $branchName,
        array $changes,
        int $ttlMinutes,
    ): void {
        if (! filled($recipient->email)) {
            return;
        }

        try {
            Mail::to((string) $recipient->email)->send(new InventoryAuditOtpMail(
                otpCode: $otpCode,
                managerName: $managerLabel,
                productName: $productName,
                branchName: $branchName,
                changes: $changes,
                ttlMinutes: $ttlMinutes,
            ));
        } catch (Throwable $exception) {
            Log::warning('OTP auditoría inventario: error al enviar email', [
                'recipient_id' => $recipient->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendWhatsApp(User $recipient, string $caption, string $otpCode, ?string $logoImage): void
    {
        $phone = $this->normalizePhone(
            filled($recipient->whatsapp_phone) ? $recipient->whatsapp_phone : $recipient->delivery_mobile_phone
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

            $this->ultramsgWhatsAppClient->sendTextMessage($phone, $otpCode);
        } catch (Throwable $exception) {
            Log::warning('OTP auditoría inventario: error al enviar WhatsApp', [
                'recipient_id' => $recipient->getKey(),
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
