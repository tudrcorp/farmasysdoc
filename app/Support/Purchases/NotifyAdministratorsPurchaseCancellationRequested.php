<?php

namespace App\Support\Purchases;

use App\Models\Purchase;
use App\Models\User;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

final class NotifyAdministratorsPurchaseCancellationRequested
{
    public function __construct(
        private readonly UltramsgWhatsAppClient $ultramsgWhatsAppClient,
    ) {}

    public function notify(Purchase $purchase, ?User $actor): void
    {
        if (! $this->ultramsgWhatsAppClient->isEnabled()) {
            Log::notice('UltraMsg deshabilitado: no se envía WhatsApp de solicitud de anulación de compra', [
                'purchase_id' => $purchase->getKey(),
            ]);

            return;
        }

        $phones = $this->resolveAdministratorPhones();

        if ($phones === []) {
            Log::notice('Compra: sin teléfonos de administradores para WhatsApp (anulación)', [
                'purchase_id' => $purchase->getKey(),
            ]);

            return;
        }

        $purchase->loadMissing(['supplier', 'branch']);

        $message = $this->buildMessage($purchase, $actor);

        foreach ($phones as $phone) {
            $this->ultramsgWhatsAppClient->sendTextMessage($phone, $message);
        }
    }

    /**
     * @return list<string>
     */
    private function resolveAdministratorPhones(): array
    {
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

    private function buildMessage(Purchase $purchase, ?User $actor): string
    {
        $creator = $actor instanceof User
            ? (filled($actor->name) ? (string) $actor->name : (string) ($actor->email ?? 'Usuario'))
            : 'Usuario';

        $roleLabel = $actor instanceof User && $actor->isAdministrator()
            ? 'Administrador'
            : ($actor instanceof User && $actor->isManager() ? 'Gerencia' : 'Usuario');

        $approveUrl = URL::temporarySignedRoute(
            'purchases.annulment.show',
            now()->addDays(7),
            ['purchase' => $purchase->getKey()],
            absolute: true,
        );

        $supplier = $purchase->supplier?->displayName() ?? '—';
        $branch = $purchase->branch?->name ?? '—';

        return implode("\n", [
            'SOLICITUD DE ANULACIÓN DE COMPRA',
            "{$roleLabel} {$creator} solicitó anular una compra.",
            '',
            'Resumen:',
            'OC: '.($purchase->purchase_number ?? '—'),
            'Proveedor: '.$supplier,
            'Sucursal: '.$branch,
            'Total: '.number_format((float) ($purchase->total ?? 0), 2, ',', '.'),
            'Fecha: '.now()->format('d/m/Y H:i'),
            '',
            'Abrir enlace para revisar y confirmar anulación (requiere sesión):',
            $approveUrl,
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
