<?php

namespace App\Filament\Resources\Formularies\Tables;

use App\Filament\Resources\Formularies\Actions\DuplicateFormularyAction;
use App\Models\Formulary;
use App\Models\FormularyCategory;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FormulariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['categories', 'plans']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Catálogo')
                    ->description(fn (Formulary $record): ?string => $record->description)
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('categories_count')
                    ->label('Categorías')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'warning')
                    ->sortable(),
                TextColumn::make('plans_count')
                    ->label('Planes')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'primary' : 'gray')
                    ->sortable(),
                TextColumn::make('requirements')
                    ->label('Requisitos')
                    ->state(fn (Formulary $record): ?string => $record->requirementsLabel())
                    ->badge()
                    ->color('warning')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Activo'),
                SelectFilter::make('category')
                    ->label('Incluye la categoría')
                    ->options(fn (): array => FormularyCategory::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas('categories', fn (Builder $inner): Builder => $inner->whereKey($data['value']))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    DuplicateFormularyAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin catálogos')
            ->emptyStateDescription('Arme un catálogo eligiendo categorías del maestro.');
    }
}
