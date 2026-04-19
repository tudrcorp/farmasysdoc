<?php

namespace App\Support\ProductTransfers;

use App\Enums\ProductTransferStatus;
use App\Models\ProductTransfer;
use App\Models\User;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Illuminate\Support\Facades\Log;

final class NotifyAdministratorsOnManagerTransferRequested
{
    public function __construct(
        private readonly UltramsgWhatsAppClient $ultramsgWhatsAppClient
    ) {}

    public function notify(ProductTransfer $transfer, ?User $actor): void
    {
        if (! $this->ultramsgWhatsAppClient->isEnabled()) {
            Log::notice('UltraMsg deshabilitado: no se envía WhatsApp de traslado', [
                'transfer_id' => $transfer->id,
                'transfer_code' => $transfer->code,
            ]);

            return;
        }

        $phones = $this->resolveAdministratorPhones();

        if ($phones === []) {
            Log::notice('Traslado sin teléfonos de administradores para WhatsApp', [
                'transfer_id' => $transfer->id,
                'transfer_code' => $transfer->code,
            ]);

            return;
        }

        $transfer->loadMissing([
            'items.product',
            'fromBranch',
            'toBranch',
        ]);

        $message = $this->buildMessage($transfer, $actor);

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

    private function buildMessage(ProductTransfer $transfer, ?User $actor): string
    {
        $creator = $actor instanceof User
            ? (filled($actor->name) ? (string) $actor->name : (string) ($actor->email ?? 'Usuario'))
            : 'Usuario';

        $creatorRole = $actor instanceof User && $actor->isAdministrator()
            ? 'Administrador'
            : ($actor instanceof User && $actor->isManager() ? 'Gerencia' : 'Usuario');

        $items = $transfer->items
            ->take(20)
            ->map(function ($item): string {
                $productName = (string) ($item->product?->name ?? 'Producto sin nombre');
                $quantity = number_format((float) ($item->quantity ?? 0), 3, '.', ',');

                return "- {$productName} | Cantidad: {$quantity}";
            })
            ->implode("\n");

        $remainingCount = max(0, $transfer->items->count() - 20);
        $remainingText = $remainingCount > 0
            ? "\n... +{$remainingCount} producto(s) adicional(es)"
            : '';

        return implode("\n", [
            'ALERTA DE TRASLADO - APROBACION ADMINISTRATIVA',
            "{$creatorRole} {$creator} acaba de registrar un traslado.",
            '',
            'Detalle del traslado:',
            "Codigo: {$transfer->code}",
            'Estado: '.ProductTransferStatus::labelForStored($transfer->status),
            'Origen: '.(string) ($transfer->fromBranch?->name ?? 'N/A'),
            'Destino: '.(string) ($transfer->toBranch?->name ?? 'N/A'),
            'Fecha y hora: '.now()->format('d/m/Y H:i'),
            '',
            'Productos solicitados:',
            ($items !== '' ? $items : '- Sin productos registrados'),
            $remainingText,
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
                // Formato local VE: 0412XXXXXXX -> +58412XXXXXXX
                $raw = '+58'.substr($digitsOnly, 1);
            } elseif (str_starts_with($digitsOnly, '58') && strlen($digitsOnly) >= 10) {
                $raw = '+'.$digitsOnly;
            } elseif (str_starts_with($digitsOnly, '4') && strlen($digitsOnly) === 10) {
                // Formato VE sin 0 inicial: 412XXXXXXX -> +58412XXXXXXX
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
