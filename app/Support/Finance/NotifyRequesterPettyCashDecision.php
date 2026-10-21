<?php

namespace App\Support\Finance;

use App\Enums\PettyCashFundStatus;
use App\Filament\Resources\PettyCashFunds\PettyCashFundResource;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

final class NotifyRequesterPettyCashDecision
{
    public function __construct(
        private readonly UltramsgWhatsAppClient $ultramsgWhatsAppClient,
    ) {}

    public function notify(PettyCashFund $fund): void
    {
        $fund->loadMissing(['branch', 'requestedBy']);

        $requester = $fund->requestedBy;
        if (! $requester instanceof User) {
            Log::notice('Caja chica: no hay solicitante para avisar de la decisión', [
                'petty_cash_fund_id' => $fund->getKey(),
            ]);

            return;
        }

        $this->notifyRequesterInPanel($fund, $requester);

        if (! $this->ultramsgWhatsAppClient->isEnabled()) {
            Log::notice('UltraMsg deshabilitado: no se envía WhatsApp de decisión de caja chica', [
                'petty_cash_fund_id' => $fund->getKey(),
            ]);

            return;
        }

        $phone = $this->normalizePhone(
            filled($requester->whatsapp_phone) ? $requester->whatsapp_phone : $requester->delivery_mobile_phone
        );

        if ($phone === null) {
            Log::notice('Caja chica: el solicitante no tiene WhatsApp configurado', [
                'petty_cash_fund_id' => $fund->getKey(),
                'user_id' => $requester->id,
            ]);

            return;
        }

        $caption = $this->buildMessage($fund, $requester);
        $logoImage = $this->ultramsgWhatsAppClient->resolveFarmadocLogoImage();

        try {
            $sentImage = false;

            if ($logoImage !== null) {
                $sentImage = $this->ultramsgWhatsAppClient->sendImageMessage($phone, $logoImage, $caption);
            }

            if (! $sentImage) {
                $this->ultramsgWhatsAppClient->sendTextMessage($phone, $caption);
            }
        } catch (Throwable $exception) {
            Log::warning('Caja chica: error al avisar al solicitante por WhatsApp', [
                'phone' => $phone,
                'petty_cash_fund_id' => $fund->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function notifyRequesterInPanel(PettyCashFund $fund, User $requester): void
    {
        $isApproved = $fund->status === PettyCashFundStatus::Approved;
        $branchName = (string) ($fund->branch?->name ?? '—');

        $notification = Notification::make()
            ->title($isApproved
                ? $fund->withReplenishmentQualifier('Caja chica aprobada')
                : $fund->withReplenishmentQualifier('Caja chica rechazada'))
            ->body($isApproved
                ? "Sucursal {$branchName}. Ya puede registrar gastos sobre este fondo."
                : 'Sucursal '.$branchName.'. '.(filled($fund->rejection_reason)
                    ? (string) $fund->rejection_reason
                    : 'Puede volver a solicitar en Caja chica.'));

        if ($isApproved) {
            $notification->success();
        } else {
            $notification->danger();
        }

        $notification->sendToDatabase($requester);
    }

    private function buildMessage(PettyCashFund $fund, User $requester): string
    {
        $name = filled($requester->name) ? (string) $requester->name : 'Gerencia';
        $panelUrl = $this->panelUrl($fund);
        $isReplenishment = $fund->isReplenishment();
        $totalAmount = PettyCashMoney::formatUsd((float) $fund->amount_usd)
            .' / '
            .PettyCashMoney::formatVes((float) $fund->amount_ves);

        if ($fund->status === PettyCashFundStatus::Approved) {
            $amountLines = $isReplenishment
                ? [
                    'Transferido (pago): '.PettyCashMoney::formatUsd($fund->transferAmountUsd())
                        .' / '.PettyCashMoney::formatVes($fund->transferAmountVes()),
                    'Total disponible en sistema: '.$totalAmount,
                ]
                : ['Monto: '.$totalAmount];

            return implode("\n", array_merge([
                mb_strtoupper($fund->withReplenishmentQualifier('Caja chica aprobada'), 'UTF-8'),
                "Hola {$name}, administración aprobó su solicitud de caja chica.",
                '',
                'Sucursal: '.(string) ($fund->branch?->name ?? '—'),
            ], $amountLines, [
                'Referencia: '.(filled($fund->payment_reference) ? (string) $fund->payment_reference : '—'),
                'Fecha: '.now()->format('d/m/Y H:i'),
                '',
                'Ya puede registrar los gastos en Farmaadmin → Caja chica:',
                $panelUrl,
            ]));
        }

        $reason = trim((string) $fund->rejection_reason);

        return implode("\n", [
            mb_strtoupper($fund->withReplenishmentQualifier('Caja chica rechazada'), 'UTF-8'),
            "Hola {$name}, administración rechazó su solicitud de caja chica.",
            '',
            'Sucursal: '.(string) ($fund->branch?->name ?? '—'),
            'Monto: '.$totalAmount,
            'Motivo: '.($reason !== '' ? $reason : '—'),
            'Fecha: '.now()->format('d/m/Y H:i'),
            '',
            'Puede volver a solicitarla en Farmaadmin → Caja chica:',
            $panelUrl,
        ]);
    }

    private function panelUrl(PettyCashFund $fund): string
    {
        try {
            return PettyCashFundResource::getUrl('view', ['record' => $fund], panel: 'farmaadmin');
        } catch (Throwable) {
            return URL::to('/farmaadmin/petty-cash-funds/'.$fund->getKey());
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
