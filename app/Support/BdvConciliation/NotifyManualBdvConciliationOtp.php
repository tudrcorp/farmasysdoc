<?php

namespace App\Support\BdvConciliation;

use App\Mail\ManualBdvConciliationOtpMail;
use App\Models\User;
use App\Support\Branches\BranchDailyOperationRecipients;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class NotifyManualBdvConciliationOtp
{
    public function __construct(
        private readonly UltramsgWhatsAppClient $ultramsgWhatsAppClient,
        private readonly BranchDailyOperationRecipients $branchRecipients,
    ) {}

    /**
     * @param  array{
     *     branch_name?: string|null,
     *     reference?: string|null,
     *     amount?: string|null,
     *     payer_document?: string|null,
     *     payer_phone?: string|null,
     *     destination_phone?: string|null,
     *     payment_date?: string|null,
     *     origin_bank?: string|null
     * }  $context
     */
    public function notify(User $actor, string $otpCode, array $context, int $ttlSeconds): void
    {
        $recipients = $this->resolveRecipients($actor);

        if ($recipients === []) {
            Log::notice('OTP conciliación manual BDV: no hay destinatarios', [
                'actor_id' => $actor->getKey(),
            ]);

            return;
        }

        $this->dispatch(
            actor: $actor,
            otpCode: $otpCode,
            context: $context,
            ttlSeconds: $ttlSeconds,
            recipients: $recipients,
            fromPosCashier: false,
        );
    }

    /**
     * Caja: OTP al gerente de la sucursal y a los administradores.
     *
     * @param  array{
     *     branch_name?: string|null,
     *     reference?: string|null,
     *     amount?: string|null,
     *     payer_document?: string|null,
     *     payer_phone?: string|null,
     *     destination_phone?: string|null,
     *     payment_date?: string|null,
     *     origin_bank?: string|null
     * }  $context
     * @return list<User>
     */
    public function notifyForPos(User $cashier, int $branchId, string $otpCode, array $context, int $ttlSeconds): array
    {
        $recipients = $this->contactablePosRecipients($branchId);

        if ($recipients === []) {
            Log::notice('OTP conciliación manual BDV (caja): no hay destinatarios contactables', [
                'cashier_id' => $cashier->getKey(),
                'branch_id' => $branchId,
            ]);

            return [];
        }

        $this->dispatch(
            actor: $cashier,
            otpCode: $otpCode,
            context: $context,
            ttlSeconds: $ttlSeconds,
            recipients: $recipients,
            fromPosCashier: true,
        );

        return $recipients;
    }

    /**
     * Gerente de la sucursal + administradores, con email o WhatsApp.
     *
     * @return list<User>
     */
    public function contactablePosRecipients(int $branchId): array
    {
        return array_values(array_filter(
            $this->resolvePosRecipients($branchId),
            fn (User $user): bool => filled($user->email)
                || filled($user->whatsapp_phone)
                || filled($user->delivery_mobile_phone),
        ));
    }

    /**
     * @param  list<User>  $recipients
     * @param  array{
     *     branch_name?: string|null,
     *     reference?: string|null,
     *     amount?: string|null,
     *     payer_document?: string|null,
     *     payer_phone?: string|null,
     *     destination_phone?: string|null,
     *     payment_date?: string|null,
     *     origin_bank?: string|null
     * }  $context
     */
    private function dispatch(
        User $actor,
        string $otpCode,
        array $context,
        int $ttlSeconds,
        array $recipients,
        bool $fromPosCashier,
    ): void {
        $ttlMinutes = max(1, (int) ceil($ttlSeconds / 60));
        $actorLabel = (string) ($actor->name ?? $actor->email ?? 'usuario');
        $actorIsAdministrator = $actor->isAdministrator();
        $caption = $this->buildWhatsAppCaption(
            otpCode: $otpCode,
            actorLabel: $actorLabel,
            actorIsAdministrator: $actorIsAdministrator,
            fromPosCashier: $fromPosCashier,
            context: $context,
            ttlMinutes: $ttlMinutes,
        );

        $whatsAppEnabled = $this->ultramsgWhatsAppClient->isEnabled();
        if (! $whatsAppEnabled) {
            Log::notice('UltraMsg deshabilitado: no se envía WhatsApp de OTP de conciliación manual', [
                'actor_id' => $actor->getKey(),
            ]);
        }

        $logoImage = $whatsAppEnabled
            ? $this->ultramsgWhatsAppClient->resolveFarmadocLogoImage()
            : null;

        foreach ($recipients as $recipient) {
            $this->sendEmail(
                recipient: $recipient,
                otpCode: $otpCode,
                actorLabel: $actorLabel,
                actorIsAdministrator: $actorIsAdministrator,
                fromPosCashier: $fromPosCashier,
                context: $context,
                ttlMinutes: $ttlMinutes,
            );

            if ($whatsAppEnabled) {
                $this->sendWhatsApp($recipient, $caption, $otpCode, $logoImage);
            }
        }
    }

    /**
     * Gerente: el propio gerente + administradores.
     * Administrador: solo administradores.
     *
     * @return list<User>
     */
    private function resolveRecipients(User $actor): array
    {
        $admins = User::query()
            ->get(['id', 'name', 'email', 'roles', 'whatsapp_phone', 'delivery_mobile_phone'])
            ->filter(fn (User $user): bool => $user->isAdministrator())
            ->values()
            ->all();

        if ($actor->isAdministrator()) {
            return $admins;
        }

        $alreadyIncluded = collect($admins)->contains(
            fn (User $admin): bool => (int) $admin->getKey() === (int) $actor->getKey(),
        );

        if ($alreadyIncluded) {
            return $admins;
        }

        $freshActor = User::query()
            ->whereKey($actor->getKey())
            ->first(['id', 'name', 'email', 'roles', 'whatsapp_phone', 'delivery_mobile_phone']);

        if (! $freshActor instanceof User) {
            return $admins;
        }

        return [$freshActor, ...$admins];
    }

    /**
     * @return list<User>
     */
    private function resolvePosRecipients(int $branchId): array
    {
        return User::query()
            ->with('managedBranches:id')
            ->get(['id', 'name', 'email', 'roles', 'branch_id', 'whatsapp_phone', 'delivery_mobile_phone'])
            ->filter(fn (User $user): bool => $this->branchRecipients->shouldNotifyUser($user, $branchId))
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *     branch_name?: string|null,
     *     reference?: string|null,
     *     amount?: string|null,
     *     payer_document?: string|null,
     *     payer_phone?: string|null,
     *     destination_phone?: string|null,
     *     payment_date?: string|null,
     *     origin_bank?: string|null
     * }  $context
     */
    private function buildWhatsAppCaption(
        string $otpCode,
        string $actorLabel,
        bool $actorIsAdministrator,
        bool $fromPosCashier,
        array $context,
        int $ttlMinutes,
    ): string {
        $intro = $this->introLine($actorLabel, $actorIsAdministrator, $fromPosCashier);

        $lines = [
            '*FARMADOC*',
            'OTP — Conciliación manual Pago Móvil',
            '',
            $intro,
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
        $lines[] = 'Válido '.$ttlMinutes.' minutos · Un solo uso';

        return implode("\n", $lines);
    }

    private function introLine(string $actorLabel, bool $actorIsAdministrator, bool $fromPosCashier): string
    {
        if ($fromPosCashier) {
            return 'El cajero '.$actorLabel.' procederá a ejecutar una conciliación manual de Pago Móvil. Entregue la clave OTP al cajero solo si autoriza.';
        }

        if ($actorIsAdministrator) {
            return 'El administrador '.$actorLabel.' procederá a ejecutar una conciliación manual de Pago Móvil.';
        }

        return 'El gerente '.$actorLabel.' procederá a ejecutar una conciliación manual de Pago Móvil.';
    }

    /**
     * @param  array{
     *     branch_name?: string|null,
     *     reference?: string|null,
     *     amount?: string|null,
     *     payer_document?: string|null,
     *     payer_phone?: string|null,
     *     destination_phone?: string|null,
     *     payment_date?: string|null,
     *     origin_bank?: string|null
     * }  $context
     * @return list<string>
     */
    private function detailLines(array $context): array
    {
        $pairs = [
            'Sucursal' => $context['branch_name'] ?? null,
            'Referencia' => $context['reference'] ?? null,
            'Monto' => $context['amount'] ?? null,
            'Doc. pagador' => $context['payer_document'] ?? null,
            'Tel. pagador' => $context['payer_phone'] ?? null,
            'Tel. destino' => $context['destination_phone'] ?? null,
            'Fecha de pago' => $context['payment_date'] ?? null,
            'Banco origen' => $context['origin_bank'] ?? null,
        ];

        $lines = [];
        foreach ($pairs as $label => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            $lines[] = $label.': '.$value;
        }

        return $lines;
    }

    /**
     * @param  array{
     *     branch_name?: string|null,
     *     reference?: string|null,
     *     amount?: string|null,
     *     payer_document?: string|null,
     *     payer_phone?: string|null,
     *     destination_phone?: string|null,
     *     payment_date?: string|null,
     *     origin_bank?: string|null
     * }  $context
     */
    private function sendEmail(
        User $recipient,
        string $otpCode,
        string $actorLabel,
        bool $actorIsAdministrator,
        bool $fromPosCashier,
        array $context,
        int $ttlMinutes,
    ): void {
        if (! filled($recipient->email)) {
            return;
        }

        try {
            Mail::to((string) $recipient->email)->send(new ManualBdvConciliationOtpMail(
                otpCode: $otpCode,
                actorName: $actorLabel,
                actorIsAdministrator: $actorIsAdministrator,
                fromPosCashier: $fromPosCashier,
                branchName: filled($context['branch_name'] ?? null) ? (string) $context['branch_name'] : null,
                reference: filled($context['reference'] ?? null) ? (string) $context['reference'] : null,
                amount: filled($context['amount'] ?? null) ? (string) $context['amount'] : null,
                payerDocument: filled($context['payer_document'] ?? null) ? (string) $context['payer_document'] : null,
                payerPhone: filled($context['payer_phone'] ?? null) ? (string) $context['payer_phone'] : null,
                destinationPhone: filled($context['destination_phone'] ?? null) ? (string) $context['destination_phone'] : null,
                paymentDate: filled($context['payment_date'] ?? null) ? (string) $context['payment_date'] : null,
                originBank: filled($context['origin_bank'] ?? null) ? (string) $context['origin_bank'] : null,
                ttlMinutes: $ttlMinutes,
            ));
        } catch (Throwable $exception) {
            Log::warning('OTP conciliación manual BDV: error al enviar email', [
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
            Log::warning('OTP conciliación manual BDV: error al enviar WhatsApp', [
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
