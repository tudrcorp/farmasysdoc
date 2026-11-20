<?php

namespace App\Filament\Resources\PartnerCompanies\Tables;

use App\Enums\UncoveredMedicineInventoryStatus;
use App\Enums\UncoveredMedicineManagementStatus;
use App\Models\PartnerUncoveredMedicine;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UncoveredMedicinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('case_number')
                    ->label('Caso')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('patient_name')
                    ->label('Paciente')
                    ->placeholder('—')
                    ->description(fn (PartnerUncoveredMedicine $record): ?string => self::patientDescription($record))
                    ->wrap(),
                TextColumn::make('medicine_name')
                    ->label('Medicamento')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap()
                    ->description(fn (PartnerUncoveredMedicine $record): ?string => $record->indications),
                TextColumn::make('quantity_label')
                    ->label('Cantidad')
                    ->placeholder('—'),
                TextColumn::make('duration_label')
                    ->label('Duración')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('management_status')
                    ->label('Estatus de gestión')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => UncoveredMedicineManagementStatus::tryLabel($state))
                    ->color(fn (?string $state): string => UncoveredMedicineManagementStatus::tryFrom((string) $state)?->color() ?? 'gray')
                    ->sortable(),
                TextColumn::make('inventory_status')
                    ->label('Inventario')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => UncoveredMedicineInventoryStatus::tryLabel($state))
                    ->color(fn (?string $state): string => UncoveredMedicineInventoryStatus::tryFrom((string) $state)?->color() ?? 'gray')
                    ->tooltip(fn (?string $state): ?string => UncoveredMedicineInventoryStatus::tryFrom((string) $state)?->description())
                    ->sortable(),
                TextColumn::make('coordination_reference')
                    ->label('Coordinación')
                    ->placeholder('—')
                    ->description(fn (PartnerUncoveredMedicine $record): ?string => self::coordinationDescription($record))
                    ->toggleable(),
                IconColumn::make('covered')
                    ->label('Cubierto')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('registered_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y h:i a')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('remote_updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y h:i a')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('management_status')
                    ->label('Estatus de gestión')
                    ->options(UncoveredMedicineManagementStatus::options()),
                SelectFilter::make('inventory_status')
                    ->label('Inventario')
                    ->options(UncoveredMedicineInventoryStatus::options()),
                Filter::make('case_number')
                    ->label('Caso')
                    ->schema([
                        TextInput::make('case_number')
                            ->label('Número de caso')
                            ->placeholder('25043-01757'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['case_number'] ?? null),
                        fn (Builder $query): Builder => $query->where('case_number', trim((string) $data['case_number'])),
                    )),
                Filter::make('registered_at')
                    ->label('Registrado')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->where('registered_at', '>=', $date.' 00:00:00'))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->where('registered_at', '<=', $date.' 23:59:59'))),
            ])
            ->defaultSort('registered_at', 'desc')
            ->emptyStateHeading('Sin medicamentos guardados')
            ->emptyStateDescription('Pulse «Actualizar información» para consultar a Tu Dr. Group. Abrir esta ficha no consume peticiones.')
            ->striped();
    }

    private static function patientDescription(PartnerUncoveredMedicine $record): ?string
    {
        $parts = array_filter([
            filled($record->patient_national_id) ? 'C.I. '.$record->patient_national_id : null,
            $record->patient_phone,
            $record->patientLocation(),
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private static function coordinationDescription(PartnerUncoveredMedicine $record): ?string
    {
        $parts = array_filter([
            $record->coordination_id !== null ? 'ID '.$record->coordination_id : null,
            filled($record->coordination_status)
                ? UncoveredMedicineManagementStatus::tryLabel($record->coordination_status)
                : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
