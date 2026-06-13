<?php

namespace App\Support\Cash;

use App\Models\PhysicalCashBox;
use App\Models\User;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NotifyOnPhysicalCashBoxOpen
{
    public function __construct(
        private readonly UltramsgWhatsAppClient $ultramsgWhatsAppClient,
    ) {}

    public function notify(User $cashier, PhysicalCashBox $physicalCashBox, float $openingUsd, float $openingVes): void
    {
        if (! $this->ultramsgWhatsAppClient->isEnabled()) {
            Log::notice('UltraMsg deshabilitado: no se envía WhatsApp de apertura de caja física', [
                'cashier_id' => $cashier->getKey(),
                'physical_cash_box_id' => $physicalCashBox->getKey(),
            ]);

            return;
        }

        $phones = $this->resolveRecipientPhones($cashier);

        if ($phones === []) {
            Log::notice('Apertura de caja física: sin teléfonos de gerentes o administradores para WhatsApp', [
                'cashier_id' => $cashier->getKey(),
                'physical_cash_box_id' => $physicalCashBox->getKey(),
            ]);

            return;
        }

        $bannerImage = $this->ultramsgWhatsAppClient->resolvePhysicalCashBoxBannerImage();
        $caption = $this->buildCaption($cashier, $physicalCashBox, $openingUsd, $openingVes);

        foreach ($phones as $phone) {
            try {
                $sent = false;

                if ($bannerImage !== null) {
                    $sent = $this->ultramsgWhatsAppClient->sendImageMessage($phone, $bannerImage, $caption);
                }

                if (! $sent) {
                    $this->ultramsgWhatsAppClient->sendTextMessage($phone, $caption);
                }
            } catch (Throwable $exception) {
                Log::warning('Apertura de caja física: error al enviar WhatsApp', [
                    'phone' => $phone,
                    'cashier_id' => $cashier->getKey(),
                    'physical_cash_box_id' => $physicalCashBox->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function resolveRecipientPhones(User $cashier): array
    {
        $cashierBranchId = filled($cashier->branch_id) ? (int) $cashier->branch_id : null;

        $phones = User::query()
            ->with('managedBranches:id')
            ->get(['id', 'roles', 'branch_id', 'whatsapp_phone', 'delivery_mobile_phone'])
            ->filter(fn (User $user): bool => $this->shouldNotifyUser($user, $cashierBranchId))
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

        return array_values(array_unique([...$phones, ...$fallbackPhones]));
    }

    private function shouldNotifyUser(User $user, ?int $cashierBranchId): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        if ($cashierBranchId === null) {
            return false;
        }

        if ($user->hasGerenciaRole()) {
            return in_array($cashierBranchId, $user->managedBranchIds(), true);
        }

        if ($user->isManager() && ! $user->isAdministrator()) {
            return filled($user->branch_id) && (int) $user->branch_id === $cashierBranchId;
        }

        return false;
    }

    private function buildCaption(User $cashier, PhysicalCashBox $physicalCashBox, float $openingUsd, float $openingVes): string
    {
        $cashier->loadMissing('branch');
        $cashierName = filled($cashier->name) ? (string) $cashier->name : (string) ($cashier->email ?? 'Cajero');
        $branchName = (string) ($cashier->branch?->name ?? 'Sin sucursal');
        $openedAt = $physicalCashBox->opened_at ?? now();
        $openedAtLabel = $openedAt->timezone((string) config('app.timezone'))->format('d/m/Y H:i');

        return implode("\n", [

            'APERTURA DE CAJA FISICA',
            (string) config('app.name'),

            '',
            '[ TURNO ]',
            'Sucursal:'.$branchName,
            'Cajero:'.$cashierName,
            'Apertura:'.$openedAtLabel,
            '',
            '[ EFECTIVO INICIAL ]',
            'USD:'.number_format($openingUsd, 2, ',', '.'),
            'VES:'.number_format($openingVes, 2, ',', '.'),
            '',
            'Reporte automatico al abrir caja fisica.',
        ]);
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
