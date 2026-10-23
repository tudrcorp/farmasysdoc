<?php

namespace App\Filament\Resources\Hr\EmployeeRequests\Tables;

use App\Enums\EmployeeRequestStatus;
use App\Enums\EmployeeRequestType;
use App\Filament\Resources\Hr\EmployeeRequests\Actions\EmployeeRequestDecisionActions;
use App\Models\EmployeeRequest;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['employee:id,first_name,last_name,national_id', 'branch:id,name'])
                ->withCount('attachments'))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('employee_name')
                    ->label('Empleado')
                    ->state(fn (EmployeeRequest $record): string => $record->employee?->fullName() ?? '—')
                    ->description(fn (EmployeeRequest $record): ?string => $record->employee?->formattedNationalId())
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('employee', function (Builder $employee) use ($search): void {
                            $employee->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('national_id', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (EmployeeRequestType $state): string => $state->label())
                    ->color(fn (EmployeeRequestType $state): string => $state->filamentColor()),
                TextColumn::make('summary')
                    ->label('Detalle')
                    ->state(fn (EmployeeRequest $record): string => $record->summaryLabel())
                    ->description(fn (EmployeeRequest $record): ?string => $record->is_early ? 'Anticipadas' : null),
                IconColumn::make('attachments_count')
                    ->label('Soportes')
                    ->state(fn (EmployeeRequest $record): bool => (int) $record->attachments_count > 0)
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-minus')
                    ->tooltip(fn (EmployeeRequest $record): ?string => (int) $record->attachments_count > 0 ? $record->attachments_count.' archivo(s)' : null)
                    ->alignCenter(),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn (EmployeeRequestStatus $state): string => $state->label())
                    ->color(fn (EmployeeRequestStatus $state): string => $state->filamentColor()),
                TextColumn::make('created_at')
                    ->label('Enviada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->label('Decidida')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(EmployeeRequestType::options()),
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options(EmployeeRequestStatus::options()),
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->fullName())
                    ->searchable(['first_name', 'last_name', 'national_id']),
            ])
            ->recordActions([
                EmployeeRequestDecisionActions::approve(),
                EmployeeRequestDecisionActions::reject(),
                ActionGroup::make([
                    ViewAction::make(),
                    EmployeeRequestDecisionActions::downloadCertificate(),
                ]),
            ]);
    }
}
