<?php

namespace App\Filament\Resources\Hr\Severance\Exits\Tables;

use App\Enums\EmployeeExitReason;
use App\Enums\EmployeeExitStatus;
use App\Models\EmployeeExit;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeExitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.last_name')
                    ->label('Empleado')
                    ->state(fn (EmployeeExit $record): string => $record->employee->fullName())
                    ->searchable(['first_name', 'last_name'])
                    ->weight('medium'),
                TextColumn::make('employee.branch.name')->label('Sucursal')->badge()->color('info'),
                TextColumn::make('reason')->label('Motivo')->formatStateUsing(fn (EmployeeExitReason $state): string => $state->label()),
                TextColumn::make('last_work_day')->label('Último día')->date('d/m/Y')->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (EmployeeExitStatus $state): string => $state->label())
                    ->color(fn (EmployeeExitStatus $state): string => $state->filamentColor()),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(EmployeeExitStatus::options()),
                SelectFilter::make('reason')->label('Motivo')->options(EmployeeExitReason::options()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
