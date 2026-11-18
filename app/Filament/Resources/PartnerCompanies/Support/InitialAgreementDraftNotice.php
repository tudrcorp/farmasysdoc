<?php

namespace App\Filament\Resources\PartnerCompanies\Support;

use App\Filament\Resources\PartnerAgreements\PartnerCompanyAgreementResource;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
use App\Models\User;
use App\Services\Partners\PartnerInitialAgreementDraftSync;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Crea/actualiza el borrador del convenio inicial al guardar el aliado y avisa al analista.
 */
final class InitialAgreementDraftNotice
{
    public static function syncAndNotify(PartnerCompany $partner): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        $result = app(PartnerInitialAgreementDraftSync::class)->sync($partner, $user);
        $agreement = $result['agreement'];

        $notification = match ($result['outcome']) {
            'created' => Notification::make()
                ->title('Convenio inicial registrado en borrador')
                ->body('Se creó el convenio '.$agreement?->number.' con las fechas del aliado. Adjunte el contrato firmado y envíelo a aprobación para iniciar el histórico de renovaciones.')
                ->success(),
            'updated' => Notification::make()
                ->title('Convenio inicial actualizado')
                ->body('El borrador '.$agreement?->number.' tomó las nuevas fechas y condiciones del aliado.')
                ->info(),
            'failed' => Notification::make()
                ->title('No se pudo registrar el convenio inicial')
                ->body($result['message'].' Complete los datos del aliado o regístrelo desde «Convenios y renovaciones».')
                ->warning()
                ->persistent(),
            default => null,
        };

        if ($notification === null) {
            return;
        }

        if ($agreement instanceof PartnerCompanyAgreement) {
            $notification->actions([
                Action::make('openAgreement')
                    ->label('Abrir convenio')
                    ->url(PartnerCompanyAgreementResource::getUrl('view', ['record' => $agreement])),
            ]);
        }

        $notification->send();
    }
}
