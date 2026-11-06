<?php

namespace App\Services\Purchases;

use App\Enums\SupplierReturnEventType;
use App\Jobs\NotifySupplierReturnJob;
use App\Mail\SupplierReturnNotificationMail;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Support\Notifications\UltramsgWhatsAppClient;
use App\Support\Notifications\WhatsAppLink;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Avisos de devoluciones por panel (campana), correo y WhatsApp.
 *
 * - Registrada / devuelta: administradores (y quien la registró, si no es quien actúa).
 * - Aprobada / rechazada: quien la registró y los gerentes de la sucursal.
 */
final class SupplierReturnNotifier
{
    public function __construct(private UltramsgWhatsAppClient $whatsApp) {}

    public function notify(SupplierReturn $return, SupplierReturnEventType $event, ?User $actor = null): void
    {
        $return->loadMissing(['branch', 'supplier', 'items']);

        foreach ($this->recipients($return, $event, $actor) as $user) {
            try {
                $this->sendPanelNotification($return, $event, $user);
            } catch (Throwable $exception) {
                Log::warning('Devoluciones: no se pudo guardar el aviso de panel', [
                    'supplier_return_id' => $return->getKey(),
                    'user_id' => $user->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }

            if (filled($user->email)) {
                NotifySupplierReturnJob::dispatch((int) $return->getKey(), (int) $user->getKey(), $event->value, NotifySupplierReturnJob::CHANNEL_MAIL);
            }

            if (WhatsAppLink::normalizePhoneDigits($user->whatsapp_phone) !== null) {
                NotifySupplierReturnJob::dispatch((int) $return->getKey(), (int) $user->getKey(), $event->value, NotifySupplierReturnJob::CHANNEL_WHATSAPP);
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function recipients(SupplierReturn $return, SupplierReturnEventType $event, ?User $actor = null): Collection
    {
        $users = User::query()->get();
        $creator = $users->first(fn (User $user): bool => (int) $user->getKey() === (int) $return->created_by);

        $recipients = match ($event) {
            SupplierReturnEventType::Approved, SupplierReturnEventType::Rejected => $users->filter(
                fn (User $user): bool => ! $user->isAdministrator()
                    && $user->isManager()
                    && in_array((int) $return->branch_id, array_map('intval', $user->restrictedBranchIdsForQueries()), true)
            ),
            default => $users->filter(fn (User $user): bool => $user->isAdministrator()),
        };

        if ($creator instanceof User) {
            $recipients = $recipients->push($creator);
        }

        return $recipients
            ->unique(fn (User $user): int => (int) $user->getKey())
            ->reject(fn (User $user): bool => $actor !== null && $user->is($actor))
            ->values();
    }

    public function sendEmail(SupplierReturn $return, User $user, SupplierReturnEventType $event): void
    {
        if (blank($user->email)) {
            return;
        }

        Mail::to((string) $user->email)->send(new SupplierReturnNotificationMail(
            supplierReturn: $return,
            recipientName: (string) $user->name,
            headline: $this->headline($return, $event),
            lines: $this->lines($return, $event),
            actionUrl: $this->url($return),
        ));
    }

    /**
     * @throws RuntimeException cuando Ultramsg rechaza el envío (la cola reintenta)
     */
    public function sendWhatsApp(SupplierReturn $return, User $user, SupplierReturnEventType $event): void
    {
        $digits = WhatsAppLink::normalizePhoneDigits($user->whatsapp_phone);

        if ($digits === null || ! $this->whatsApp->isEnabled()) {
            return;
        }

        $body = implode("\n", [
            '*Farmadoc · Devoluciones a proveedor*',
            '',
            'Hola, '.$user->name.'.',
            '*'.$this->headline($return, $event).'*',
            ...$this->lines($return, $event),
            '',
            $this->url($return),
        ]);

        if (! $this->whatsApp->sendTextMessage($digits, $body)) {
            throw new RuntimeException('No se pudo enviar el WhatsApp de la devolución #'.$return->getKey().'.');
        }
    }

    public function headline(SupplierReturn $return, SupplierReturnEventType $event): string
    {
        $branch = $return->branch?->name ?? 'sucursal';

        return match ($event) {
            SupplierReturnEventType::Created => "Devolución {$return->number} por revisar ({$branch})",
            SupplierReturnEventType::Approved => "Devolución {$return->number} aprobada: entregar al proveedor",
            SupplierReturnEventType::Rejected => "Devolución {$return->number} rechazada",
            SupplierReturnEventType::Returned => "Devolución {$return->number} entregada al proveedor",
            default => "Devolución {$return->number}: ".mb_strtolower($event->label()),
        };
    }

    /**
     * @return list<string>
     */
    public function lines(SupplierReturn $return, SupplierReturnEventType $event): array
    {
        $lines = [
            'Proveedor: '.$return->supplierName(),
            'Sucursal: '.($return->branch?->name ?? '—'),
        ];

        foreach ($return->items->take(5) as $item) {
            $lines[] = sprintf('• %s: %s de más', $item->product_name, self::quantity((float) $item->quantity_excess));
        }

        if ($return->items->count() > 5) {
            $lines[] = '• y '.($return->items->count() - 5).' producto(s) más';
        }

        $extra = match ($event) {
            SupplierReturnEventType::Created => 'Nota: '.$return->notes,
            SupplierReturnEventType::Rejected => 'Motivo: '.$return->rejection_reason,
            SupplierReturnEventType::Approved => filled($return->review_notes) ? 'Observación: '.$return->review_notes : null,
            SupplierReturnEventType::Returned => 'Entregada el '.$return->returned_on?->format('d/m/Y'),
            default => null,
        };

        if (filled($extra)) {
            $lines[] = $extra;
        }

        return $lines;
    }

    public function url(SupplierReturn $return): string
    {
        return route('filament.farmaadmin.resources.supplier-returns.view', ['record' => $return]);
    }

    public static function quantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, ',', '.'), '0'), ',');
    }

    private function sendPanelNotification(SupplierReturn $return, SupplierReturnEventType $event, User $user): void
    {
        $notification = Notification::make()
            ->title($this->headline($return, $event))
            ->body(implode(' · ', $this->lines($return, $event)))
            ->actions([
                Action::make('open')->label('Ver devolución')->url($this->url($return))->markAsRead(),
            ]);

        match ($event) {
            SupplierReturnEventType::Returned => $notification->success(),
            SupplierReturnEventType::Rejected => $notification->danger(),
            SupplierReturnEventType::Approved => $notification->info(),
            default => $notification->warning(),
        };

        $notification->sendToDatabase($user);
    }
}
