<?php

namespace App\Support\Sales;

use App\Mail\PosDiscountOtpMail;
use App\Models\User;
use App\Support\Branches\BranchDailyOperationRecipients;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía la OTP de descuento en caja al gerente de la sucursal y a los administradores (email y WhatsApp).
 */
final class NotifyPosDiscountOtp
{
    public function __construct(
        private readonly UltramsgWhatsAppClient $ultramsgWhatsAppClient,
        private readonly BranchDailyOperationRecipients $branchRecipients,
    ) {}

    /**
     * @param  array{
     *     branch_name?: string|null,
     *     client_name?: string|null,
     *     sale_percent?: string|null,
     *     lines?: list<string>,
     *     discount_amount?: string|null,
     *     total?: string|null
     * }  $context
     * @return list<User>
     */
    public function notify(User $cashier, int $branchId, string $otpCode, array $context, int $ttlSeconds): array
    {
        $recipients = $this->contactableRecipients($branchId);

        if ($recipients === []) {
            Log::notice('OTP descuento en caja: no hay destinatarios contactables', [
                'cashier_id' => $cashier->getKey(),
                'branch_id' => $branchId,
            ]);

            return [];
        }

        $ttlMinutes = max(1, (int) ceil($ttlSeconds / 60));
        $cashierName = (string) ($cashier->name ?? $cashier->email ?? 'usuario');
        $caption = $this->buildWhatsAppCaption($otpCode, $cashierName, $context, $ttlMinutes);

        $whatsAppEnabled = $this->ultramsgWhatsAppClient->isEnabled();
        if (! $whatsAppEnabled) {
            Log::notice('UltraMsg deshabilitado: no se envía WhatsApp de OTP de descuento en caja', [
                'cashier_id' => $cashier->getKey(),
            ]);
        }

        $logoImage = $whatsAppEnabled
            ? $this->ultramsgWhatsAppClient->resolveFarmadocLogoImage()
            : null;

        foreach ($recipients as $recipient) {
            $this->sendEmail($recipient, $otpCode, $cashierName, $context, $ttlMinutes);

            if ($whatsAppEnabled) {
                $this->sendWhatsApp($recipient, $caption, $otpCode, $logoImage);
            }
        }

        return $recipients;
    }

    /**
     * Gerente de la sucursal + administradores, con email o WhatsApp.
     *
     * @return list<User>
     */
    public function contactableRecipients(int $branchId): array
    {
        return User::query()
            ->with('managedBranches:id')
            ->get(['id', 'name', 'email', 'roles', 'branch_id', 'whatsapp_phone', 'delivery_mobile_phone'])
            ->filter(fn (User $user): bool => $this->branchRecipients->shouldNotifyUser($user, $branchId))
            ->filter(fn (User $user): bool => filled($user->email)
                || filled($user->whatsapp_phone)
                || filled($user->delivery_mobile_phone))
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *     branch_name?: string|null,
     *     client_name?: string|null,
     *     sale_percent?: string|null,
     *     lines?: list<string>,
     *     discount_amount?: string|null,
     *     total?: string|null
     * }  $context
     */
    private function buildWhatsAppCaption(string $otpCode, string $cashierName, array $context, int $ttlMinutes): string
    {
        $lines = [
            '*FARMADOC*',
            'OTP — Venta con descuento en caja',
            '',
            'El cajero '.$cashierName.' solicita autorización para registrar una venta con descuento. Entregue la clave OTP solo si autoriza.',
            '',
        ];

        foreach ($this->detailLines($context) as $line) {
            $lines[] = $line;
        }

        $lines[] = '';
        $lines[] = 'Clave (mantén pulsado para copiar):';
        $lines[] = '';
        $lines[] = $otpCode;
        $lines[] = '';
        $lines[] = 'Válido '.$ttlMinutes.' minutos · Un solo uso · Solo para este descuento';

        return implode("\n", $lines);
    }

    /**
     * @param  array{
     *     branch_name?: string|null,
     *     client_name?: string|null,
     *     sale_percent?: string|null,
     *     lines?: list<string>,
     *     discount_amount?: string|null,
     *     total?: string|null
     * }  $context
     * @return list<string>
     */
    private function detailLines(array $context): array
    {
        $pairs = [
            'Sucursal' => $context['branch_name'] ?? null,
            'Cliente' => $context['client_name'] ?? null,
            'Descuento sobre el total' => $context['sale_percent'] ?? null,
            'Monto descontado' => $context['discount_amount'] ?? null,
            'Total a cobrar' => $context['total'] ?? null,
        ];

        $lines = [];
        foreach ($pairs as $label => $value) {
            if (is_string($value) && $value !== '') {
                $lines[] = $label.': '.$value;
            }
        }

        $productLines = $context['lines'] ?? [];
        if ($productLines !== []) {
            $lines[] = 'Productos con descuento:';
            foreach ($productLines as $productLine) {
                $lines[] = '• '.$productLine;
            }
        }

        return $lines;
    }

    /**
     * @param  array{
     *     branch_name?: string|null,
     *     client_name?: string|null,
     *     sale_percent?: string|null,
     *     lines?: list<string>,
     *     discount_amount?: string|null,
     *     total?: string|null
     * }  $context
     */
    private function sendEmail(User $recipient, string $otpCode, string $cashierName, array $context, int $ttlMinutes): void
    {
        if (! filled($recipient->email)) {
            return;
        }

        try {
            Mail::to((string) $recipient->email)->send(new PosDiscountOtpMail(
                otpCode: $otpCode,
                cashierName: $cashierName,
                branchName: filled($context['branch_name'] ?? null) ? (string) $context['branch_name'] : null,
                clientName: filled($context['client_name'] ?? null) ? (string) $context['client_name'] : null,
                salePercent: filled($context['sale_percent'] ?? null) ? (string) $context['sale_percent'] : null,
                discountedLines: $context['lines'] ?? [],
                discountAmount: filled($context['discount_amount'] ?? null) ? (string) $context['discount_amount'] : null,
                total: filled($context['total'] ?? null) ? (string) $context['total'] : null,
                ttlMinutes: $ttlMinutes,
            ));
        } catch (Throwable $exception) {
            Log::warning('OTP descuento en caja: error al enviar email', [
                'recipient_id' => $recipient->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendWhatsApp(User $recipient, string $caption, string $otpCode, ?string $logoImage): void
    {
        $phone = $this->branchRecipients->normalizePhone(
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
            Log::warning('OTP descuento en caja: error al enviar WhatsApp', [
                'recipient_id' => $recipient->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
