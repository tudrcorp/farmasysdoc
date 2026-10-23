<?php

namespace App\Filament\Resources\Hr\EmployeeRequests\Schemas;

use App\Enums\EmployeeRequestStatus;
use App\Enums\EmployeeRequestType;
use App\Enums\EmploymentCertificateSalaryBasis;
use App\Filament\Resources\Hr\EmployeeRequests\Actions\EmployeeRequestDecisionActions;
use App\Models\EmployeeRequest;
use App\Models\EmployeeRequestAttachment;
use App\Services\Hr\EmploymentCertificatePdfFactory;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EmployeeRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Solicitud')
                    ->icon(Heroicon::Inbox)
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('type')
                                ->label('Tipo')
                                ->badge()
                                ->formatStateUsing(fn (EmployeeRequestType $state): string => $state->label())
                                ->color(fn (EmployeeRequestType $state): string => $state->filamentColor()),
                            TextEntry::make('status')
                                ->label('Estatus')
                                ->badge()
                                ->formatStateUsing(fn (EmployeeRequestStatus $state): string => $state->label())
                                ->color(fn (EmployeeRequestStatus $state): string => $state->filamentColor()),
                            TextEntry::make('created_at')
                                ->label('Enviada')
                                ->dateTime('d/m/Y H:i'),
                            TextEntry::make('period')
                                ->label('Fechas')
                                ->state(fn (EmployeeRequest $record): ?string => $record->periodLabel())
                                ->visible(fn (EmployeeRequest $record): bool => $record->type->hasDateRange()),
                            TextEntry::make('days_count')
                                ->label(fn (EmployeeRequest $record): string => $record->type === EmployeeRequestType::Vacation ? 'Días hábiles' : 'Días')
                                ->visible(fn (EmployeeRequest $record): bool => $record->type->hasDateRange()),
                            TextEntry::make('entitled_days')
                                ->label('Días que le corresponden')
                                ->visible(fn (EmployeeRequest $record): bool => $record->type === EmployeeRequestType::Vacation),
                            TextEntry::make('entitlement_starts_on')
                                ->label('Derecho nace el')
                                ->date('d/m/Y')
                                ->badge()
                                ->color(fn (EmployeeRequest $record): string => $record->is_early ? 'warning' : 'success')
                                ->helperText(fn (EmployeeRequest $record): ?string => $record->is_early ? 'Vacaciones anticipadas: se piden antes de que nazca el derecho.' : null)
                                ->visible(fn (EmployeeRequest $record): bool => $record->type === EmployeeRequestType::Vacation),
                            TextEntry::make('salary_basis')
                                ->label('Sueldo en la constancia')
                                ->formatStateUsing(fn (EmploymentCertificateSalaryBasis $state): string => $state->label())
                                ->visible(fn (EmployeeRequest $record): bool => $record->type === EmployeeRequestType::EmploymentCertificate),
                            TextEntry::make('salary_amount')
                                ->label('Monto que se imprime')
                                ->state(fn (EmployeeRequest $record): ?string => $record->employee !== null && $record->salary_basis !== null
                                    ? app(EmploymentCertificatePdfFactory::class)->salaryLabel($record->employee, $record->salary_basis)
                                    : null)
                                ->visible(fn (EmployeeRequest $record): bool => $record->type === EmployeeRequestType::EmploymentCertificate),
                        ]),
                        TextEntry::make('reason')
                            ->label('Motivo del empleado')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Empleado')
                    ->icon(Heroicon::User)
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('employee_name')
                                ->label('Nombre')
                                ->state(fn (EmployeeRequest $record): string => $record->employee?->fullName() ?? '—'),
                            TextEntry::make('employee.national_id')
                                ->label('Cédula'),
                            TextEntry::make('branch.name')
                                ->label('Sucursal')
                                ->placeholder('—'),
                            TextEntry::make('employee.job_title')
                                ->label('Cargo')
                                ->placeholder('Sin cargo'),
                            TextEntry::make('employee.hired_at')
                                ->label('Fecha de ingreso')
                                ->date('d/m/Y')
                                ->placeholder('Sin fecha'),
                            TextEntry::make('service')
                                ->label('Antigüedad')
                                ->state(fn (EmployeeRequest $record): ?string => $record->employee?->serviceDurationLabel())
                                ->placeholder('—'),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Soportes')
                    ->icon(Heroicon::PaperClip)
                    ->visible(fn (EmployeeRequest $record): bool => $record->attachments()->exists())
                    ->schema([
                        RepeatableEntry::make('attachments')
                            ->hiddenLabel()
                            ->contained(false)
                            ->schema([
                                TextEntry::make('original_name')
                                    ->hiddenLabel()
                                    ->icon(fn (EmployeeRequestAttachment $record): Heroicon => $record->isImage() ? Heroicon::Photo : Heroicon::DocumentText)
                                    ->helperText(fn (EmployeeRequestAttachment $record): string => $record->sizeLabel())
                                    ->suffixAction(
                                        Action::make('download')
                                            ->icon(Heroicon::ArrowDownTray)
                                            ->tooltip('Descargar')
                                            ->action(fn (EmployeeRequestAttachment $record) => EmployeeRequestDecisionActions::downloadAttachment($record)),
                                    ),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Decisión')
                    ->icon(Heroicon::CheckBadge)
                    ->visible(fn (EmployeeRequest $record): bool => $record->reviewed_at !== null || $record->cancelled_at !== null)
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('reviewer.name')
                                ->label('Decidida por')
                                ->placeholder('—'),
                            TextEntry::make('reviewed_at')
                                ->label('Fecha')
                                ->dateTime('d/m/Y H:i')
                                ->placeholder('—'),
                            TextEntry::make('cancelled_at')
                                ->label('Anulada por el empleado')
                                ->dateTime('d/m/Y H:i')
                                ->visible(fn (EmployeeRequest $record): bool => $record->cancelled_at !== null),
                        ]),
                        TextEntry::make('admin_note')
                            ->label('Nota para el empleado')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
