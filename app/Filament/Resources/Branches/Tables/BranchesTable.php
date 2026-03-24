<?php

namespace App\Filament\Resources\Branches\Tables;

use App\Models\Branch;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BranchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Código copiado'),
                TextColumn::make('name')
                    ->label('Nombre comercial')
                    ->description(fn (Branch $record): ?string => filled($record->legal_name) ? $record->legal_name : null)
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('location')
                    ->label('Ciudad / Depto.')
                    ->state(fn (Branch $record): string => self::formatLocation($record))
                    ->sortable(['city', 'state'])
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->where(function (Builder $q) use ($search): void {
                            $q->where('city', 'like', "%{$search}%")
                                ->orWhere('state', 'like', "%{$search}%");
                        });
                    })
                    ->placeholder('—'),
                TextColumn::make('country')
                    ->label('País')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon(Heroicon::Envelope)
                    ->iconColor('gray')
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('phones')
                    ->label('Teléfonos')
                    ->state(fn (Branch $record): string => self::formatPhones($record))
                    ->sortable(['phone'])
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->where(function (Builder $q) use ($search): void {
                            $q->where('phone', 'like', "%{$search}%")
                                ->orWhere('mobile_phone', 'like', "%{$search}%");
                        });
                    })
                    ->icon(Heroicon::Phone)
                    ->iconColor('gray')
                    ->placeholder('—'),
                IconColumn::make('is_headquarters')
                    ->label('Sede')
                    ->boolean()
                    ->trueIcon(Heroicon::Star)
                    ->falseIcon(Heroicon::MinusSmall)
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->tooltip(fn (Branch $record): string => $record->is_headquarters
                        ? 'Sede principal'
                        : 'No es sede principal'),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle)
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter()
                    ->tooltip(fn (Branch $record): string => $record->is_active
                        ? 'Sucursal activa'
                        : 'Sucursal inactiva'),
                TextColumn::make('legal_name')
                    ->label('Razón social')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax_id')
                    ->label('NIT / ID fiscal')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->label('Teléfono fijo')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mobile_phone')
                    ->label('Celular / WhatsApp')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->limit(45)
                    ->tooltip(function (Branch $record): ?string {
                        return filled($record->address) ? $record->address : null;
                    })
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado operativo')
                    ->placeholder('Todas')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),
                TernaryFilter::make('is_headquarters')
                    ->label('Sede principal')
                    ->placeholder('Todas')
                    ->trueLabel('Solo sede principal')
                    ->falseLabel('Sin sede principal'),
                SelectFilter::make('country')
                    ->label('País')
                    ->options(fn (): array => Branch::query()
                        ->whereNotNull('country')
                        ->distinct()
                        ->orderBy('country')
                        ->pluck('country', 'country')
                        ->all())
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->icon(Heroicon::Eye),
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::PencilSquare),
            ])
            ->recordActionsColumnLabel('Acciones')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function formatLocation(Branch $branch): string
    {
        $parts = array_filter([$branch->city, $branch->state]);

        return $parts ? implode(', ', $parts) : '—';
    }

    private static function formatPhones(Branch $branch): string
    {
        $parts = array_filter([$branch->phone, $branch->mobile_phone]);

        return $parts ? implode(' · ', $parts) : '—';
    }
}
