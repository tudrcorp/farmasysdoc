<?php

namespace App\Filament\Resources\Hr\Training\Enrollments\Tables;

use App\Enums\TrainingAttendance;
use App\Filament\Resources\Hr\Training\Enrollments\Actions\TrainingEnrollmentActions;
use App\Filament\Resources\Hr\Training\Enrollments\TrainingEnrollmentResource;
use App\Models\TrainingEnrollment;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrainingEnrollmentsTable
{
    public static function configure(Table $table, bool $showSession = false): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.last_name')
                    ->label('Empleado')
                    ->state(fn (TrainingEnrollment $record): string => $record->employee->fullName())
                    ->searchable(['first_name', 'last_name'])
                    ->weight('medium'),
                TextColumn::make('employee.branch.name')->label('Sucursal')->badge()->color('info'),
                TextColumn::make('session.course.name')->label('Curso')->visible($showSession)->wrap(),
                TextColumn::make('session.starts_at')->label('Fecha')->date('d/m/Y')->visible($showSession),
                TextColumn::make('attendance')
                    ->label('Asistencia')
                    ->badge()
                    ->formatStateUsing(fn (TrainingAttendance $state): string => $state->label())
                    ->color(fn (TrainingAttendance $state): string => $state->filamentColor()),
                TextColumn::make('result')
                    ->label('Resultado')
                    ->state(fn (TrainingEnrollment $record): ?string => self::resultLabel($record))
                    ->placeholder('—'),
                TextColumn::make('expires_on')->label('Vence')->date('d/m/Y')->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('attendance')->label('Asistencia')->options(TrainingAttendance::options()),
                Filter::make('expiring')
                    ->label('Por vencer (30 días)')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('expires_on', [
                        now()->toDateString(),
                        now()->addDays(TrainingEnrollmentResource::EXPIRY_WARNING_DAYS)->toDateString(),
                    ])),
                Filter::make('expired')
                    ->label('Vencidas')
                    ->query(fn (Builder $query): Builder => $query->whereDate('expires_on', '<', now()->toDateString())),
            ])
            ->recordActions([
                TrainingEnrollmentActions::certificate(),
                ActionGroup::make([
                    TrainingEnrollmentActions::mark('markAttended', TrainingAttendance::Attended),
                    TrainingEnrollmentActions::mark('markAbsent', TrainingAttendance::Absent),
                    TrainingEnrollmentActions::mark('markExcused', TrainingAttendance::Excused),
                    TrainingEnrollmentActions::recordResult(),
                    TrainingEnrollmentActions::withdraw(),
                ]),
            ])
            ->toolbarActions([
                TrainingEnrollmentActions::bulkMark('bulkAttended', TrainingAttendance::Attended),
                TrainingEnrollmentActions::bulkMark('bulkAbsent', TrainingAttendance::Absent),
            ]);
    }

    private static function resultLabel(TrainingEnrollment $enrollment): ?string
    {
        if ($enrollment->passed === null) {
            return null;
        }

        $label = $enrollment->passed ? 'Aprobado' : 'Reprobado';

        return $enrollment->score !== null ? $label.' · '.($enrollment->score + 0) : $label;
    }
}
