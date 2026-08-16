<?php

namespace App\Filament\Resources\Hr\Employees\Actions;

use App\Models\Employee;
use App\Services\Hr\EmployeePortalLinkGenerator;
use App\Support\Notifications\UltramsgWhatsAppClient;
use App\Support\Notifications\WhatsAppLink;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

final class RequestEmployeeFileEnrollmentAction
{
    public const NAME = 'inviteEmployeePortal';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('Portal del empleado')
            ->icon(Heroicon::QrCode)
            ->color('info')
            ->modalWidth(Width::Medium)
            ->modalHeading('Invitar al portal')
            ->modalDescription('El portal está siempre disponible. El empleado entra con su cédula o teléfono, y puede crear una clave si quiere.')
            ->modalContent(function (Employee $record) {
                return view(
                    'filament.resources.hr.employees.partials.portal-invite',
                    app(EmployeePortalLinkGenerator::class)->inviteViewData($record),
                );
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->visible(fn (?Employee $record): bool => $record instanceof Employee && $record->is_active)
            ->extraModalFooterActions([
                Action::make('openWhatsApp')
                    ->label('Abrir WhatsApp')
                    ->icon(Heroicon::ChatBubbleLeftRight)
                    ->color('gray')
                    ->url(function (Employee $record): ?string {
                        $invite = app(EmployeePortalLinkGenerator::class)->inviteViewData($record);

                        return $invite['whatsappUrl'];
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (Employee $record): bool => WhatsAppLink::normalizePhoneDigits($record->phone) !== null),
                Action::make('sendWhatsApp')
                    ->label('Enviar por WhatsApp')
                    ->icon(Heroicon::PaperAirplane)
                    ->color('success')
                    ->visible(fn (Employee $record): bool => app(UltramsgWhatsAppClient::class)->isEnabled()
                        && WhatsAppLink::normalizePhoneDigits($record->phone) !== null)
                    ->action(function (Employee $record): void {
                        self::sendWhatsAppInvitation($record);
                    }),
            ]);
    }

    private static function sendWhatsAppInvitation(Employee $record): void
    {
        $digits = WhatsAppLink::normalizePhoneDigits($record->phone);
        $generator = app(EmployeePortalLinkGenerator::class);
        $url = $generator->temporaryUrl($record);

        if ($digits === null) {
            Notification::make()
                ->title('Sin teléfono')
                ->body('Este empleado no tiene un número válido para WhatsApp.')
                ->danger()
                ->send();

            return;
        }

        $sent = app(UltramsgWhatsAppClient::class)->sendTextMessage(
            $digits,
            $generator->invitationMessage($record, $url),
        );

        if (! $sent) {
            Notification::make()
                ->title('No se pudo enviar')
                ->body('Revisa UltraMsg o usa «Abrir WhatsApp».')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('WhatsApp enviado')
            ->body('El empleado recibió el enlace al portal.')
            ->success()
            ->send();
    }
}
