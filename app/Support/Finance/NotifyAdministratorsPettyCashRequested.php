<?php

namespace App\Support\Finance;

use App\Models\PettyCashFund;
use App\Models\User;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

final class NotifyAdministratorsPettyCashRequested
{
    public function __construct(
        private readonly UltramsgWhatsAppClient $ultramsgWhatsAppClient,
    ) {}

    public function notify(PettyCashFund $fund, ?User $actor): void
    {
        if (! $this->ultramsgWhatsAppClient->isEnabled()) {
            Log::notice('UltraMsg deshabilitado: no se envía WhatsApp de solicitud de caja chica', [
                'petty_cash_fund_id' => $fund->getKey(),
            ]);

            return;
        }

        $phones = $this->resolveAdministratorPhones();

        if ($phones === []) {
            Log::notice('Caja chica: sin teléfonos de administradores para WhatsApp', [
                'petty_cash_fund_id' => $fund->getKey(),
            ]);

            return;
        }

        $fund->loadMissing(['branch', 'requestedBy']);

        $caption = $this->buildMessage($fund, $actor);
        $logoImage = $this->ultramsgWhatsAppClient->resolveFarmadocLogoImage();

        foreach ($phones as $phone) {
            try {
                $sentImage = false;

                if ($logoImage !== null) {
                    $sentImage = $this->ultramsgWhatsAppClient->sendImageMessage($phone, $logoImage, $caption);
                }

                if (! $sentImage) {
                    $this->ultramsgWhatsAppClient->sendTextMessage($phone, $caption);
                }
            } catch (Throwable $exception) {
                Log::warning('Caja chica: error al enviar WhatsApp', [
                    'phone' => $phone,
                    'petty_cash_fund_id' => $fund->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function signedApprovalUrl(PettyCashFund $fund): string
    {
        return URL::temporarySignedRoute(
            'petty-cash.approval.show',
            now()->addDays(7),
            ['fund' => $fund->getKey()],
            absolute: true,
        );
    }

    /**
     * @return list<string>
     */
    private function resolveAdministratorPhones(): array
    {
        $pettyCashApproverPhones = collect(
            explode(',', (string) config('petty_cash.approver_whatsapp_phones', ''))
        )
            ->map(fn (string $phone): ?string => $this->normalizePhone($phone))
            ->filter()
            ->values()
            ->all();

        if ($pettyCashApproverPhones !== []) {
            return array_values(array_unique($pettyCashApproverPhones));
        }

        $configuredApproverId = (int) config('petty_cash.approver_user_id');
        if ($configuredApproverId > 0) {
            $approverPhones = $this->phonesForUserIds([$configuredApproverId]);
            if ($approverPhones !== []) {
                return $approverPhones;
            }
        }

        $adminPhones = User::query()
            ->get(['roles', 'whatsapp_phone', 'delivery_mobile_phone'])
            ->filter(fn (User $user): bool => $user->isAdministrator())
            ->map(fn (User $user): ?string => $this->normalizePhone(
                filled($user->whatsapp_phone) ? $user->whatsapp_phone : $user->delivery_mobile_phone
            ))
            ->filter()
            ->values()
            ->all();

        $fallbackPhones = collect(
            explode(',', (string) config('services.ultramsg.admin_fallback_phones', ''))
        )
            ->map(fn (string $phone): ?string => $this->normalizePhone($phone))
            ->filter()
            ->values()
            ->all();

        return array_values(array_unique([...$adminPhones, ...$fallbackPhones]));
    }

    /**
     * @param  list<int>  $userIds
     * @return list<string>
     */
    private function phonesForUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->get(['whatsapp_phone', 'delivery_mobile_phone'])
            ->map(fn (User $user): ?string => $this->normalizePhone(
                filled($user->whatsapp_phone) ? $user->whatsapp_phone : $user->delivery_mobile_phone
            ))
            ->filter()
            ->values()
            ->all();
    }

    private function buildMessage(PettyCashFund $fund, ?User $actor): string
    {
        $creator = $actor instanceof User
            ? (filled($actor->name) ? (string) $actor->name : (string) ($actor->email ?? 'Usuario'))
            : (string) ($fund->requestedBy?->name ?? 'Gerencia');

        $approveUrl = $this->signedApprovalUrl($fund);

        $notes = trim((string) $fund->notes);
        if (mb_strlen($notes) > 280) {
            $notes = mb_substr($notes, 0, 279).'…';
        }

        $isReplenishment = $fund->isReplenishment();
        $heading = mb_strtoupper($fund->withReplenishmentQualifier('Solicitud de caja chica'), 'UTF-8');
        $intro = $isReplenishment
            ? "Gerencia {$creator} solicitó reposición de caja chica."
            : "Gerencia {$creator} pidió un fondo de caja chica.";

        $lines = [
            $heading,
            $intro,
            '',
            'Resumen:',
            'Sucursal: '.(string) ($fund->branch?->name ?? '—'),
        ];

        if ($isReplenishment) {
            $lines[] = 'Monto a transferir (pago): '.PettyCashMoney::formatUsd($fund->transferAmountUsd())
                .' / '.PettyCashMoney::formatVes($fund->transferAmountVes());
            if ((float) $fund->carried_over_usd > 0.009) {
                $lines[] = 'Saldo anterior (solo en sistema): '.PettyCashMoney::formatUsd((float) $fund->carried_over_usd)
                    .' / '.PettyCashMoney::formatVes((float) $fund->carried_over_ves);
            }
            $lines[] = 'Total del fondo tras aprobar: '.PettyCashMoney::formatUsd((float) $fund->amount_usd)
                .' / '.PettyCashMoney::formatVes((float) $fund->amount_ves);
            $lines[] = 'Caja anterior: #'.$fund->replenished_from_fund_id;
        } else {
            $lines[] = 'Monto: '.PettyCashMoney::formatUsd((float) $fund->amount_usd).' / '.PettyCashMoney::formatVes((float) $fund->amount_ves);
        }

        $lines[] = 'Cuenta: '.$fund->bankAccountSummary();
        $lines[] = 'Titular: '.(filled($fund->bank_account_holder) ? (string) $fund->bank_account_holder : '—');
        $lines[] = 'Cédula: '.(filled($fund->holder_national_id) ? (string) $fund->holder_national_id : '—');
        $lines[] = 'Teléfono: '.(filled($fund->holder_phone) ? (string) $fund->holder_phone : '—');
        $lines[] = 'Motivo: '.($notes !== '' ? $notes : '—');
        $lines[] = 'Fecha: '.($fund->requested_at?->format('d/m/Y') ?? now()->format('d/m/Y'));
        $lines[] = '';
        $lines[] = 'Abra el enlace para ver el detalle y aprobar o rechazar (también puede hacerlo en Farmaadmin → Caja chica):';
        $lines[] = $approveUrl;

        return implode("\n", $lines);
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
