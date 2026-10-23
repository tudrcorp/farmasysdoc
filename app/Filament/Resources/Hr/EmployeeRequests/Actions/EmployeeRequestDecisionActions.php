<?php

namespace App\Filament\Resources\Hr\EmployeeRequests\Actions;

use App\Filament\Resources\Hr\EmployeeRequests\EmployeeRequestResource;
use App\Models\EmployeeRequest;
use App\Models\EmployeeRequestAttachment;
use App\Models\User;
use App\Services\Hr\EmployeeRequestReviewer;
use App\Services\Hr\EmploymentCertificatePdfFactory;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Acciones compartidas por la tabla y la vista de solicitudes: aprobar, rechazar y descargas.
 */
final class EmployeeRequestDecisionActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Aprobar')
            ->icon(Heroicon::CheckCircle)
            ->color('success')
            ->modalHeading('Aprobar solicitud')
            ->modalDescription('El empleado recibe el aviso por correo y WhatsApp.')
            ->modalSubmitActionLabel('Aprobar')
            ->schema([
                Textarea::make('note')
                    ->label('Nota para el empleado (opcional)')
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->visible(fn (EmployeeRequest $record): bool => self::canDecide($record))
            ->action(function (EmployeeRequest $record, array $data): void {
                try {
                    app(EmployeeRequestReviewer::class)->approve($record, self::adminUser(), $data['note'] ?? null);
                    Notification::make()->title('Solicitud aprobada')->body('Le avisamos al empleado.')->success()->send();
                } catch (Throwable $exception) {
                    Notification::make()->title('No se pudo aprobar')->body($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Rechazar')
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->modalHeading('Rechazar solicitud')
            ->modalDescription('El motivo le llega al empleado por correo y WhatsApp.')
            ->modalSubmitActionLabel('Rechazar')
            ->schema([
                Textarea::make('note')
                    ->label('Motivo del rechazo')
                    ->required()
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->visible(fn (EmployeeRequest $record): bool => self::canDecide($record))
            ->action(function (EmployeeRequest $record, array $data): void {
                try {
                    app(EmployeeRequestReviewer::class)->reject($record, self::adminUser(), (string) ($data['note'] ?? ''));
                    Notification::make()->title('Solicitud rechazada')->body('Le avisamos al empleado.')->success()->send();
                } catch (Throwable $exception) {
                    Notification::make()->title('No se pudo rechazar')->body($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function downloadCertificate(): Action
    {
        return Action::make('downloadCertificate')
            ->label('Ver constancia')
            ->icon(Heroicon::ArrowDownTray)
            ->color('gray')
            ->visible(fn (EmployeeRequest $record): bool => $record->canDownloadCertificate())
            ->action(fn (EmployeeRequest $record): StreamedResponse => app(EmploymentCertificatePdfFactory::class)->download($record));
    }

    public static function downloadAttachment(EmployeeRequestAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk(EmployeeRequestAttachment::DISK);

        if (! $disk->exists($attachment->path)) {
            throw new RuntimeException('El archivo ya no existe.');
        }

        return $disk->download($attachment->path, $attachment->original_name);
    }

    public static function canDecide(EmployeeRequest $record): bool
    {
        return $record->isPending()
            && (EmployeeRequestResource::currentUser()?->canAccessHrModule() ?? false);
    }

    private static function adminUser(): User
    {
        $user = EmployeeRequestResource::currentUser();

        if (! $user instanceof User) {
            throw new RuntimeException('Usuario no autenticado.');
        }

        return $user;
    }
}
