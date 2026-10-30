<?php

namespace App\Filament\Resources\Hr\Recruitment\Applications\Actions;

use App\Enums\RecruitmentRequestStatus;
use App\Enums\RecruitmentStage;
use App\Filament\Resources\Hr\Employees\EmployeeResource;
use App\Filament\Resources\Hr\Recruitment\Applications\RecruitmentApplicationResource;
use App\Models\Branch;
use App\Models\RecruitmentApplication;
use App\Services\Recruitment\RecruitmentHireService;
use App\Services\Recruitment\RecruitmentSelectionService;
use App\Support\Recruitment\RecruitmentNationalId;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Throwable;

final class RecruitmentApplicationActions
{
    public static function moveStage(): Action
    {
        return Action::make('moveStage')
            ->label('Cambiar etapa')
            ->icon(Heroicon::ArrowsRightLeft)
            ->color('gray')
            ->modalSubmitActionLabel('Cambiar')
            ->visible(fn (RecruitmentApplication $record): bool => $record->stage !== RecruitmentStage::Hired
                && $record->recruitmentRequest->status === RecruitmentRequestStatus::Approved)
            ->schema([
                Select::make('stage')
                    ->label('Etapa')
                    ->options(collect(RecruitmentStage::open())->mapWithKeys(fn (RecruitmentStage $stage): array => [$stage->value => $stage->label()])->all())
                    ->default(fn (RecruitmentApplication $record): string => $record->stage->value)
                    ->native(false)
                    ->required(),
                Textarea::make('note')->label('Nota (opcional)')->rows(3)->maxLength(2000),
            ])
            ->action(self::guarded('No se pudo cambiar la etapa', function (RecruitmentApplication $record, array $data): void {
                app(RecruitmentSelectionService::class)->move(
                    $record,
                    RecruitmentStage::from($data['stage']),
                    RecruitmentApplicationResource::currentUser(),
                    $data['note'] ?? null,
                );
                Notification::make()->title('Etapa actualizada')->success()->send();
            }));
    }

    public static function evaluate(): Action
    {
        return Action::make('evaluate')
            ->label('Entrevista y calificación')
            ->icon(Heroicon::Star)
            ->color('gray')
            ->fillForm(fn (RecruitmentApplication $record): array => [
                'interview_at' => $record->interview_at,
                'rating' => $record->rating,
            ])
            ->visible(fn (RecruitmentApplication $record): bool => $record->stage->isOpen())
            ->schema([
                DateTimePicker::make('interview_at')->label('Fecha de entrevista')->native(false)->seconds(false)->displayFormat('d/m/Y h:i a'),
                Select::make('rating')
                    ->label('Calificación')
                    ->options([1 => '1 — Muy bajo', 2 => '2 — Bajo', 3 => '3 — Aceptable', 4 => '4 — Bueno', 5 => '5 — Excelente'])
                    ->native(false),
                Textarea::make('note')->label('Nota (opcional)')->rows(3)->maxLength(2000),
            ])
            ->action(self::guarded('No se pudo guardar', function (RecruitmentApplication $record, array $data): void {
                $record->update(['interview_at' => $data['interview_at'] ?? null, 'rating' => $data['rating'] ?? null]);

                if (filled($data['note'] ?? null)) {
                    app(RecruitmentSelectionService::class)->addNote($record, $data['note'], RecruitmentApplicationResource::currentUser());
                }

                Notification::make()->title('Evaluación guardada')->success()->send();
            }));
    }

    public static function addNote(): Action
    {
        return Action::make('addNote')
            ->label('Agregar nota')
            ->icon(Heroicon::ChatBubbleLeftEllipsis)
            ->color('gray')
            ->schema([
                Textarea::make('note')->label('Nota')->required()->rows(4)->maxLength(2000),
            ])
            ->action(self::guarded('No se pudo guardar', function (RecruitmentApplication $record, array $data): void {
                app(RecruitmentSelectionService::class)->addNote($record, $data['note'], RecruitmentApplicationResource::currentUser());
                Notification::make()->title('Nota agregada')->success()->send();
            }));
    }

    public static function discard(): Action
    {
        return Action::make('discard')
            ->label('Descartar')
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->modalHeading('Descartar candidato')
            ->modalDescription('El candidato sigue en el banco para futuras vacantes.')
            ->modalSubmitActionLabel('Descartar')
            ->visible(fn (RecruitmentApplication $record): bool => $record->stage->isOpen())
            ->schema([
                Textarea::make('reason')->label('Motivo')->required()->rows(3)->maxLength(1000),
            ])
            ->action(self::guarded('No se pudo descartar', function (RecruitmentApplication $record, array $data): void {
                app(RecruitmentSelectionService::class)->discard($record, $data['reason'], RecruitmentApplicationResource::currentUser());
                Notification::make()->title('Candidato descartado')->success()->send();
            }));
    }

    public static function hire(): Action
    {
        return Action::make('hire')
            ->label('Contratar')
            ->icon(Heroicon::CheckBadge)
            ->color('success')
            ->modalHeading('Contratar y crear empleado')
            ->modalDescription('Los datos personales, la foto y el CV pasan a la ficha del empleado.')
            ->modalSubmitActionLabel('Contratar')
            ->visible(fn (RecruitmentApplication $record): bool => $record->stage === RecruitmentStage::Offer)
            ->fillForm(fn (RecruitmentApplication $record): array => [
                'branch_id' => $record->recruitmentRequest->branch_id,
                'job_title' => $record->recruitmentRequest->job_title,
                'hired_at' => now()->toDateString(),
                'monthly_salary_usd' => $record->recruitmentRequest->reference_salary_usd,
            ])
            ->schema([
                Callout::make('Esta cédula ya existe en empleados')
                    ->description('Se actualizará y reactivará esa ficha en lugar de crear otra.')
                    ->icon(Heroicon::ExclamationTriangle)
                    ->color('warning')
                    ->visible(fn (RecruitmentApplication $record): bool => RecruitmentNationalId::findEmployee($record->candidate->national_id) !== null),
                Grid::make(2)->schema([
                    Select::make('branch_id')
                        ->label('Sucursal')
                        ->options(fn (): array => Branch::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('job_title')->label('Cargo')->required()->maxLength(160),
                    DatePicker::make('hired_at')->label('Fecha de ingreso')->native(false)->displayFormat('d/m/Y')->required(),
                    TextInput::make('monthly_salary_usd')->label('Sueldo mensual (USD)')->numeric()->minValue(0.01)->step(0.01)->prefix('US$')->required(),
                    TextInput::make('legal_salary_ves')->label('Sueldo de ley (VES)')->numeric()->minValue(0.01)->step(0.01)->prefix('Bs')->required(),
                ]),
            ])
            ->action(self::guarded('No se pudo contratar', function (RecruitmentApplication $record, array $data, Page $livewire): void {
                $employee = app(RecruitmentHireService::class)->hire($record, $data, RecruitmentApplicationResource::currentUser());

                Notification::make()
                    ->title('Empleado creado')
                    ->body('Completa sus datos bancarios y la forma de pago en su ficha.')
                    ->success()
                    ->send();

                $livewire->redirect(EmployeeResource::getUrl('edit', ['record' => $employee]));
            }));
    }

    private static function guarded(string $failureTitle, Closure $handler): Closure
    {
        return function (RecruitmentApplication $record, array $data, Page $livewire) use ($failureTitle, $handler): void {
            try {
                $handler($record, $data, $livewire);
            } catch (Throwable $exception) {
                Notification::make()->title($failureTitle)->body($exception->getMessage())->danger()->send();
            }
        };
    }
}
