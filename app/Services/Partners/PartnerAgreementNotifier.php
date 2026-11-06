<?php

namespace App\Services\Partners;

use App\Enums\PartnerAgreementEventType;
use App\Jobs\NotifyPartnerAgreementJob;
use App\Mail\PartnerAgreementNotificationMail;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
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
 * Avisa a los involucrados de un convenio por panel (campana), correo y WhatsApp (Ultramsg).
 *
 * Involucrados: administradores, quien creó/envió/aprobó el convenio y los gerentes con acceso al módulo
 * en las sucursales del aliado. El aviso de panel se guarda en el momento; correo y WhatsApp van por cola
 * (un job por destinatario y canal, después del commit) para que un fallo no repita otros envíos.
 */
final class PartnerAgreementNotifier
{
    public function __construct(private UltramsgWhatsAppClient $whatsApp) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function notify(PartnerCompanyAgreement $agreement, PartnerAgreementEventType $event, ?User $actor = null, array $context = []): void
    {
        $agreement->loadMissing('partnerCompany.branches');

        foreach ($this->recipients($agreement, $event, $actor) as $user) {
            try {
                $this->sendPanelNotification($agreement, $event, $user, $context);
            } catch (Throwable $exception) {
                Log::warning('Convenios: no se pudo guardar el aviso de panel', [
                    'agreement_id' => $agreement->getKey(),
                    'user_id' => $user->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }

            if (filled($user->email)) {
                NotifyPartnerAgreementJob::dispatch((int) $agreement->getKey(), (int) $user->getKey(), $event->value, NotifyPartnerAgreementJob::CHANNEL_MAIL, $context);
            }

            if (WhatsAppLink::normalizePhoneDigits($user->whatsapp_phone) !== null) {
                NotifyPartnerAgreementJob::dispatch((int) $agreement->getKey(), (int) $user->getKey(), $event->value, NotifyPartnerAgreementJob::CHANNEL_WHATSAPP, $context);
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function recipients(PartnerCompanyAgreement $agreement, PartnerAgreementEventType $event, ?User $actor = null): Collection
    {
        $users = User::query()->get();

        $administrators = $users->filter(fn (User $user): bool => $user->isAdministrator());

        $directlyInvolvedIds = array_filter([
            $agreement->created_by,
            $agreement->submitted_by,
            $agreement->approved_by,
        ]);

        $recipients = $administrators->merge(
            $users->filter(fn (User $user): bool => in_array($user->getKey(), $directlyInvolvedIds, true))
        );

        if ($event !== PartnerAgreementEventType::Submitted) {
            $recipients = $recipients->merge(
                $users->filter(fn (User $user): bool => $this->isBranchManagerOf($user, $agreement->partnerCompany))
            );
        }

        return $recipients
            ->unique(fn (User $user): int => (int) $user->getKey())
            ->reject(fn (User $user): bool => $actor !== null && $user->is($actor))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendEmail(PartnerCompanyAgreement $agreement, User $user, PartnerAgreementEventType $event, array $context): void
    {
        if (blank($user->email)) {
            return;
        }

        Mail::to((string) $user->email)->send(new PartnerAgreementNotificationMail(
            agreement: $agreement,
            recipientName: (string) $user->name,
            headline: $this->headline($agreement, $event, $context),
            lines: $this->lines($agreement, $event, $context),
            actionUrl: $this->agreementUrl($agreement),
        ));
    }

    /**
     * @param  array<string, mixed>  $context
     *
     * @throws RuntimeException cuando Ultramsg rechaza el envío (la cola reintenta)
     */
    public function sendWhatsApp(PartnerCompanyAgreement $agreement, User $user, PartnerAgreementEventType $event, array $context): void
    {
        $digits = WhatsAppLink::normalizePhoneDigits($user->whatsapp_phone);

        if ($digits === null || ! $this->whatsApp->isEnabled()) {
            return;
        }

        $body = implode("\n", [
            '*Farmadoc · Convenios de aliados*',
            '',
            'Hola, '.$user->name.'.',
            '*'.$this->headline($agreement, $event, $context).'*',
            ...$this->lines($agreement, $event, $context),
            '',
            $this->agreementUrl($agreement),
        ]);

        if (! $this->whatsApp->sendTextMessage($digits, $body)) {
            throw new RuntimeException('No se pudo enviar el WhatsApp del convenio #'.$agreement->getKey().'.');
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function headline(PartnerCompanyAgreement $agreement, PartnerAgreementEventType $event, array $context = []): string
    {
        $number = $agreement->number ?? 'borrador';
        $partner = $agreement->partnerCompany?->legal_name ?? 'aliado';

        return match ($event) {
            PartnerAgreementEventType::Submitted => "Convenio {$number} de {$partner} por aprobar",
            PartnerAgreementEventType::Approved => "Convenio {$number} de {$partner} aprobado",
            PartnerAgreementEventType::Rejected => "Convenio {$number} de {$partner} rechazado",
            PartnerAgreementEventType::Activated => "Convenio {$number} de {$partner} entró en vigencia",
            PartnerAgreementEventType::Expired => "Venció el convenio {$number} de {$partner}",
            PartnerAgreementEventType::AutoRenewed => "Convenio de {$partner} renovado automáticamente ({$number})",
            PartnerAgreementEventType::Cancelled => "Convenio {$number} de {$partner} anulado",
            PartnerAgreementEventType::ExpiryNoticeSent => $this->expiryHeadline($number, $partner, (int) ($context['days'] ?? $agreement->daysUntilEnd())),
            default => "Convenio {$number} de {$partner}: ".mb_strtolower($event->label()),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    public function lines(PartnerCompanyAgreement $agreement, PartnerAgreementEventType $event, array $context = []): array
    {
        $lines = [
            'Tipo: '.$agreement->type->label(),
            'Vigencia: '.$agreement->termLabel(),
            'Modalidad: '.$agreement->payment_modality->label()
                .($agreement->usesCreditLimit() ? ' · Límite $'.number_format((float) $agreement->credit_limit_usd, 2, ',', '.') : ''),
        ];

        $reason = match ($event) {
            PartnerAgreementEventType::Rejected => $agreement->rejection_reason,
            PartnerAgreementEventType::Cancelled => $agreement->cancellation_reason,
            default => null,
        };

        if (filled($reason)) {
            $lines[] = 'Motivo: '.$reason;
        }

        if ($event === PartnerAgreementEventType::ExpiryNoticeSent) {
            $lines[] = $agreement->auto_renews
                ? 'Se renovará automáticamente con las mismas condiciones si no se gestiona antes.'
                : 'No tiene renovación automática: gestione la renovación antes del vencimiento.';
        }

        if ($event === PartnerAgreementEventType::Expired) {
            $lines[] = 'El aliado queda sin convenio vigente hasta que se registre una renovación.';
        }

        return $lines;
    }

    private function expiryHeadline(string $number, string $partner, int $days): string
    {
        return match (true) {
            $days <= 0 => "El convenio {$number} de {$partner} vence hoy",
            $days === 1 => "El convenio {$number} de {$partner} vence mañana",
            default => "El convenio {$number} de {$partner} vence en {$days} días",
        };
    }

    public function agreementUrl(PartnerCompanyAgreement $agreement): string
    {
        return route('filament.farmaadmin.resources.partner-agreements.view', ['record' => $agreement]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function sendPanelNotification(PartnerCompanyAgreement $agreement, PartnerAgreementEventType $event, User $user, array $context): void
    {
        $notification = Notification::make()
            ->title($this->headline($agreement, $event, $context))
            ->body(implode(' · ', $this->lines($agreement, $event, $context)))
            ->actions([
                Action::make('open')
                    ->label('Ver convenio')
                    ->url($this->agreementUrl($agreement))
                    ->markAsRead(),
            ]);

        match ($event) {
            PartnerAgreementEventType::Approved, PartnerAgreementEventType::Activated, PartnerAgreementEventType::AutoRenewed => $notification->success(),
            PartnerAgreementEventType::Rejected, PartnerAgreementEventType::Expired, PartnerAgreementEventType::Cancelled => $notification->danger(),
            default => $notification->warning(),
        };

        $notification->sendToDatabase($user);
    }

    private function isBranchManagerOf(User $user, ?PartnerCompany $partner): bool
    {
        if (! $partner instanceof PartnerCompany || ! $user->isManager() || ! $user->canAccessFarmaadminMenuKey('partner_agreements')) {
            return false;
        }

        $partnerBranchIds = $partner->branches->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($partnerBranchIds === []) {
            return true;
        }

        return array_intersect($partnerBranchIds, array_map('intval', $user->restrictedBranchIdsForQueries())) !== [];
    }
}
