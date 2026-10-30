<?php

namespace App\Filament\Resources\Hr\Training\Sessions\Actions;

use App\Enums\TrainingSessionStatus;
use App\Filament\Resources\Hr\Training\Sessions\TrainingSessionResource;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\TrainingSession;
use App\Services\Training\TrainingEnrollmentService;
use App\Services\Training\TrainingProgressService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Throwable;

final class TrainingSessionActions
{
    public static function enroll(): Action
    {
        return Action::make('enroll')
            ->label('Inscribir empleados')
            ->icon(Heroicon::UserPlus)
            ->color('primary')
            ->modalHeading('Inscribir empleados')
            ->modalDescription('Combina las opciones: se inscribe a todos los empleados activos que coincidan.')
            ->modalSubmitActionLabel('Inscribir')
            ->visible(fn (TrainingSession $record): bool => self::canManage() && $record->status === TrainingSessionStatus::Scheduled)
            ->schema([
                Select::make('branch_ids')
                    ->label('Por sucursal')
                    ->multiple()
                    ->options(fn (): array => Branch::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->live(),
                Select::make('job_titles')
                    ->label('Por cargo')
                    ->multiple()
                    ->options(fn (): array => Employee::query()
                        ->where('is_active', true)
                        ->whereNotNull('job_title')
                        ->distinct()
                        ->orderBy('job_title')
                        ->pluck('job_title', 'job_title')
                        ->all())
                    ->searchable()
                    ->live(),
                Select::make('employee_ids')
                    ->label('Empleados específicos')
                    ->multiple()
                    ->options(fn (): array => Employee::query()
                        ->where('is_active', true)
                        ->orderBy('last_name')
                        ->get()
                        ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->fullName()])
                        ->all())
                    ->searchable()
                    ->live(),
                Callout::make(fn (Get $get): string => count(self::selectedEmployeeIds($get('branch_ids'), $get('job_titles'), $get('employee_ids'))).' empleados seleccionados')
                    ->description(fn (Get $get, TrainingSession $record): ?string => self::warningsText($record, $get))
                    ->color(fn (Get $get, TrainingSession $record): string => filled(self::warningsText($record, $get)) ? 'warning' : 'info'),
            ])
            ->action(self::guarded('No se pudo inscribir', function (TrainingSession $record, array $data): void {
                $ids = self::selectedEmployeeIds($data['branch_ids'] ?? [], $data['job_titles'] ?? [], $data['employee_ids'] ?? []);

                if ($ids === []) {
                    Notification::make()->title('No seleccionaste a nadie')->warning()->send();

                    return;
                }

                $warnings = app(TrainingEnrollmentService::class)->warnings($record, $ids);
                $result = app(TrainingEnrollmentService::class)->enroll($record, $ids);

                Notification::make()
                    ->title("{$result['enrolled']} inscritos")
                    ->body(implode(' ', [
                        ...($result['already_enrolled'] > 0 ? ["{$result['already_enrolled']} ya estaban inscritos."] : []),
                        ...$warnings,
                    ]) ?: null)
                    ->status($warnings === [] ? 'success' : 'warning')
                    ->send();
            }));
    }

    public static function start(): Action
    {
        return Action::make('start')
            ->label('Iniciar')
            ->icon(Heroicon::Play)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Iniciar capacitación')
            ->visible(fn (TrainingSession $record): bool => self::canManage() && $record->status === TrainingSessionStatus::Scheduled)
            ->action(self::guarded('No se pudo iniciar', function (TrainingSession $record): void {
                app(TrainingProgressService::class)->start($record);
                Notification::make()->title('Capacitación en curso')->success()->send();
            }));
    }

    public static function finish(): Action
    {
        return Action::make('finish')
            ->label('Finalizar')
            ->icon(Heroicon::CheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Finalizar capacitación')
            ->modalDescription('Después no se podrán cambiar la asistencia ni los resultados, y se habilitan los certificados.')
            ->visible(fn (TrainingSession $record): bool => self::canManage() && $record->status->isOpen())
            ->action(self::guarded('No se pudo finalizar', function (TrainingSession $record): void {
                app(TrainingProgressService::class)->finish($record);
                Notification::make()->title('Capacitación finalizada')->success()->send();
            }));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancelar')
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancelar capacitación')
            ->modalDescription('Se avisa a los inscritos por correo y WhatsApp.')
            ->modalSubmitActionLabel('Cancelar capacitación')
            ->modalCancelActionLabel('Volver')
            ->visible(fn (TrainingSession $record): bool => self::canManage() && $record->status->isOpen())
            ->action(self::guarded('No se pudo cancelar', function (TrainingSession $record): void {
                app(TrainingProgressService::class)->cancel($record);
                Notification::make()->title('Capacitación cancelada')->success()->send();
            }));
    }

    /**
     * @return list<int>
     */
    private static function selectedEmployeeIds(mixed $branchIds, mixed $jobTitles, mixed $employeeIds): array
    {
        $branchIds = array_filter((array) $branchIds);
        $jobTitles = array_filter((array) $jobTitles);
        $ids = array_map('intval', array_filter((array) $employeeIds));

        if ($branchIds !== [] || $jobTitles !== []) {
            $ids = [...$ids, ...Employee::query()
                ->where('is_active', true)
                ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($jobTitles !== [], fn ($query) => $query->whereIn('job_title', $jobTitles))
                ->pluck('id')
                ->all()];
        }

        return array_values(array_unique($ids));
    }

    private static function warningsText(TrainingSession $session, Get $get): ?string
    {
        $ids = self::selectedEmployeeIds($get('branch_ids'), $get('job_titles'), $get('employee_ids'));
        $warnings = $ids === [] ? [] : app(TrainingEnrollmentService::class)->warnings($session, $ids);

        return $warnings === [] ? null : implode(' ', $warnings);
    }

    private static function canManage(): bool
    {
        return TrainingSessionResource::currentUser()?->canManageTraining() ?? false;
    }

    private static function guarded(string $failureTitle, Closure $handler): Closure
    {
        return function (TrainingSession $record, array $data = []) use ($failureTitle, $handler): void {
            try {
                $handler($record, $data);
            } catch (Throwable $exception) {
                Notification::make()->title($failureTitle)->body($exception->getMessage())->danger()->send();
            }
        };
    }
}
