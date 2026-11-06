<?php

namespace App\Filament\Resources\PartnerAgreements\Actions;

use App\Enums\PartnerAgreementStatus;
use App\Enums\PartnerAgreementType;
use App\Filament\Resources\PartnerAgreements\PartnerCompanyAgreementResource;
use App\Models\PartnerCompanyAgreement;
use App\Models\User;
use App\Services\Partners\PartnerAgreementException;
use App\Services\Partners\PartnerAgreementWorkflow;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Acciones del ciclo de vida de un convenio. Toda la lógica vive en {@see PartnerAgreementWorkflow};
 * aquí solo se decide la visibilidad y se traducen las excepciones de negocio en notificaciones.
 */
final class PartnerAgreementActions
{
    /**
     * @return list<Action|ActionGroup>
     */
    public static function all(): array
    {
        return [
            self::submit(),
            self::approve(),
            self::reject(),
            ActionGroup::make([
                self::startNew('renew', 'Renovar', PartnerAgreementType::Renewal, Heroicon::ArrowPath),
                self::startNew('amend', 'Modificar (adenda)', PartnerAgreementType::Amendment, Heroicon::PencilSquare),
                self::startNew('renegotiate', 'Renegociar', PartnerAgreementType::Renegotiation, Heroicon::Scale),
            ])
                ->label('Nueva versión')
                ->icon(Heroicon::DocumentDuplicate)
                ->button()
                ->color('gray'),
            self::cancel(),
        ];
    }

    public static function submit(): Action
    {
        return Action::make('submit')
            ->label('Enviar a aprobación')
            ->icon(Heroicon::PaperAirplane)
            ->color('primary')
            ->visible(fn (PartnerCompanyAgreement $record): bool => $record->status->isEditable())
            ->requiresConfirmation()
            ->modalDescription('Se avisará a los administradores. Mientras esté por aprobar no se puede editar.')
            ->action(self::run(fn (PartnerAgreementWorkflow $workflow, PartnerCompanyAgreement $record, User $user) => $workflow->submit($record, $user), 'Convenio enviado a aprobación'));
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Aprobar')
            ->icon(Heroicon::CheckBadge)
            ->color('success')
            ->visible(fn (PartnerCompanyAgreement $record): bool => $record->status === PartnerAgreementStatus::PendingApproval && self::isAdministrator())
            ->requiresConfirmation()
            ->modalHeading('Aprobar convenio')
            ->modalDescription(fn (PartnerCompanyAgreement $record): string => $record->starts_on->isFuture()
                ? 'Quedará aprobado y entrará en vigencia el '.$record->starts_on->format('d/m/Y').'.'
                : 'Entra en vigencia ahora: sus condiciones se copian a la ficha del aliado y la versión anterior queda reemplazada.')
            ->schema([
                Textarea::make('notes')->label('Observaciones (opcional)')->rows(2),
            ])
            ->action(self::run(fn (PartnerAgreementWorkflow $workflow, PartnerCompanyAgreement $record, User $user, array $data) => $workflow->approve($record, $user, $data['notes'] ?? null), 'Convenio aprobado'));
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Rechazar')
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->visible(fn (PartnerCompanyAgreement $record): bool => $record->status === PartnerAgreementStatus::PendingApproval && self::isAdministrator())
            ->modalHeading('Rechazar convenio')
            ->schema([
                Textarea::make('reason')->label('Motivo del rechazo')->required()->rows(3),
            ])
            ->action(self::run(fn (PartnerAgreementWorkflow $workflow, PartnerCompanyAgreement $record, User $user, array $data) => $workflow->reject($record, $user, (string) $data['reason']), 'Convenio rechazado'));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label(fn (PartnerCompanyAgreement $record): string => $record->status === PartnerAgreementStatus::Active ? 'Terminar anticipadamente' : 'Anular')
            ->icon(Heroicon::NoSymbol)
            ->color('danger')
            ->outlined()
            ->visible(fn (PartnerCompanyAgreement $record): bool => self::isAdministrator()
                && in_array($record->status, [...PartnerAgreementStatus::inFlight(), PartnerAgreementStatus::Active], true))
            ->modalHeading('Anular convenio')
            ->modalDescription(fn (PartnerCompanyAgreement $record): string => $record->status === PartnerAgreementStatus::Active
                ? 'El aliado quedará sin convenio vigente. Las condiciones ya aplicadas a su ficha no se revierten.'
                : 'El convenio quedará anulado y no podrá reactivarse.')
            ->schema([
                Textarea::make('reason')->label('Motivo')->required()->rows(3),
            ])
            ->action(self::run(fn (PartnerAgreementWorkflow $workflow, PartnerCompanyAgreement $record, User $user, array $data) => $workflow->cancel($record, $user, (string) $data['reason']), 'Convenio anulado'));
    }

    public static function startNew(string $name, string $label, PartnerAgreementType $type, Heroicon $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->visible(fn (PartnerCompanyAgreement $record): bool => self::canStartFrom($record, $type))
            ->url(fn (PartnerCompanyAgreement $record): string => PartnerCompanyAgreementResource::getUrl('create', [
                'partner' => $record->partner_company_id,
                'type' => $type->value,
            ]));
    }

    public static function viewContract(): Action
    {
        return Action::make('viewContract')
            ->label('Ver contrato')
            ->icon(Heroicon::PaperClip)
            ->color('gray')
            ->visible(fn (PartnerCompanyAgreement $record): bool => filled($record->signed_document_path))
            ->url(fn (PartnerCompanyAgreement $record): ?string => $record->signedDocumentUrl())
            ->openUrlInNewTab();
    }

    private static function canStartFrom(PartnerCompanyAgreement $record, PartnerAgreementType $type): bool
    {
        $isLatestClosed = $record->status === PartnerAgreementStatus::Expired
            && ! PartnerCompanyAgreement::query()
                ->where('partner_company_id', $record->partner_company_id)
                ->whereIn('status', [PartnerAgreementStatus::Active->value, PartnerAgreementStatus::Approved->value])
                ->exists();

        $allowed = $record->status === PartnerAgreementStatus::Active
            || ($type === PartnerAgreementType::Renewal && $isLatestClosed);

        if (! $allowed) {
            return false;
        }

        return ! PartnerCompanyAgreement::query()
            ->where('partner_company_id', $record->partner_company_id)
            ->whereIn('status', array_map(fn (PartnerAgreementStatus $status): string => $status->value, PartnerAgreementStatus::inFlight()))
            ->exists();
    }

    /**
     * @param  Closure(PartnerAgreementWorkflow, PartnerCompanyAgreement, User, array<string, mixed>): mixed  $operation
     */
    private static function run(Closure $operation, string $successTitle): Closure
    {
        return function (PartnerCompanyAgreement $record, array $data, Action $action) use ($operation, $successTitle): void {
            $user = Auth::user();

            if (! $user instanceof User) {
                $action->halt();
            }

            try {
                $operation(app(PartnerAgreementWorkflow::class), $record, $user, $data);
            } catch (PartnerAgreementException $exception) {
                Notification::make()->title('No se pudo completar')->body($exception->getMessage())->danger()->persistent()->send();
                $action->halt();
            }

            Notification::make()->title($successTitle)->success()->send();
            $record->refresh();
        };
    }

    private static function isAdministrator(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdministrator();
    }
}
