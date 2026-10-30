<?php

namespace App\Filament\Resources\Hr\Training\Enrollments\Actions;

use App\Enums\TrainingAttendance;
use App\Enums\TrainingSessionStatus;
use App\Filament\Resources\Hr\Training\Enrollments\TrainingEnrollmentResource;
use App\Models\TrainingEnrollment;
use App\Services\Training\TrainingEnrollmentService;
use App\Services\Training\TrainingProgressService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Throwable;

final class TrainingEnrollmentActions
{
    public static function mark(string $name, TrainingAttendance $attendance): Action
    {
        return Action::make($name)
            ->label('Marcar: '.$attendance->label())
            ->icon(Heroicon::ClipboardDocumentCheck)
            ->color($attendance->filamentColor())
            ->visible(fn (TrainingEnrollment $record): bool => self::canRecord($record))
            ->action(function (TrainingEnrollment $record) use ($attendance): void {
                try {
                    app(TrainingProgressService::class)->markAttendance($record, $attendance, TrainingEnrollmentResource::currentUser());
                    Notification::make()->title('Asistencia guardada')->success()->send();
                } catch (Throwable $exception) {
                    Notification::make()->title('No se pudo guardar')->body($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function bulkMark(string $name, TrainingAttendance $attendance): BulkAction
    {
        return BulkAction::make($name)
            ->label('Marcar: '.$attendance->label())
            ->icon(Heroicon::ClipboardDocumentCheck)
            ->color($attendance->filamentColor())
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) use ($attendance): void {
                $saved = 0;
                $lastError = null;

                foreach ($records as $record) {
                    try {
                        app(TrainingProgressService::class)->markAttendance($record, $attendance, TrainingEnrollmentResource::currentUser());
                        $saved++;
                    } catch (Throwable $exception) {
                        $lastError = $exception->getMessage();
                    }
                }

                Notification::make()
                    ->title("{$saved} de {$records->count()} guardados")
                    ->body($lastError)
                    ->status($lastError === null ? 'success' : 'warning')
                    ->send();
            });
    }

    public static function recordResult(): Action
    {
        return Action::make('recordResult')
            ->label('Registrar resultado')
            ->icon(Heroicon::AcademicCap)
            ->color('primary')
            ->fillForm(fn (TrainingEnrollment $record): array => ['passed' => $record->passed ?? true, 'score' => $record->score])
            ->visible(fn (TrainingEnrollment $record): bool => self::canRecord($record)
                && $record->session->course->requires_evaluation
                && $record->attendance === TrainingAttendance::Attended)
            ->schema([
                Toggle::make('passed')->label('Aprobó')->inline(false),
                TextInput::make('score')->label('Nota (opcional)')->numeric()->minValue(0)->maxValue(100)->step(0.01),
            ])
            ->action(function (TrainingEnrollment $record, array $data): void {
                try {
                    app(TrainingProgressService::class)->recordResult(
                        $record,
                        (bool) $data['passed'],
                        filled($data['score'] ?? null) ? (float) $data['score'] : null,
                        TrainingEnrollmentResource::currentUser(),
                    );
                    Notification::make()->title('Resultado guardado')->success()->send();
                } catch (Throwable $exception) {
                    Notification::make()->title('No se pudo guardar')->body($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function withdraw(): Action
    {
        return Action::make('withdraw')
            ->label('Retirar de la capacitación')
            ->icon(Heroicon::UserMinus)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (TrainingEnrollment $record): bool => (TrainingEnrollmentResource::currentUser()?->canManageTraining() ?? false)
                && $record->session->status === TrainingSessionStatus::Scheduled)
            ->action(function (TrainingEnrollment $record): void {
                try {
                    app(TrainingEnrollmentService::class)->withdraw($record);
                    Notification::make()->title('Empleado retirado')->success()->send();
                } catch (Throwable $exception) {
                    Notification::make()->title('No se pudo retirar')->body($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function certificate(): Action
    {
        return Action::make('certificate')
            ->label('Certificado')
            ->icon(Heroicon::DocumentArrowDown)
            ->color('success')
            ->url(fn (TrainingEnrollment $record): string => route('training.certificate', $record), shouldOpenInNewTab: true)
            ->visible(fn (TrainingEnrollment $record): bool => $record->canDownloadCertificate());
    }

    private static function canRecord(TrainingEnrollment $enrollment): bool
    {
        return $enrollment->session->status->isOpen() && ! $enrollment->session->starts_at->isFuture();
    }
}
