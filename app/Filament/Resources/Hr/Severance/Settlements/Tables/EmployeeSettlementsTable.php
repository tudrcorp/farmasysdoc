<?php

namespace App\Filament\Resources\Hr\Severance\Settlements\Tables;

use App\Enums\EmployeeSettlementStatus;
use App\Models\EmployeeSettlement;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeSettlementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('N.°')->formatStateUsing(fn (int $state): string => 'LIQ-'.str_pad((string) $state, 6, '0', STR_PAD_LEFT)),
                TextColumn::make('employee')
                    ->label('Empleado')
                    ->state(fn (EmployeeSettlement $record): string => $record->employeeExit->employee->fullName())
                    ->weight('medium'),
                TextColumn::make('last_work_day')->label('Último día')->date('d/m/Y')->sortable(),
                TextColumn::make('net_usd')->label('Neto (USD)')->numeric(2, ',', '.')->alignEnd(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (EmployeeSettlementStatus $state): string => $state->label())
                    ->color(fn (EmployeeSettlementStatus $state): string => $state->filamentColor()),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(EmployeeSettlementStatus::options()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
