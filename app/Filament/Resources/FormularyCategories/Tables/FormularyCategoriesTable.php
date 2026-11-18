<?php

namespace App\Filament\Resources\FormularyCategories\Tables;

use App\Models\ActiveIngredient;
use App\Models\FormularyCategory;
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

class FormularyCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['items', 'formularies']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Categoría')
                    ->description(fn (FormularyCategory $record): ?string => $record->description)
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('items_count')
                    ->label('Presentaciones')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'warning')
                    ->sortable(),
                TextColumn::make('formularies_count')
                    ->label('Catálogos')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'primary' : 'gray')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Activa'),
                SelectFilter::make('active_ingredient')
                    ->label('Contiene el principio activo')
                    ->options(fn (): array => ActiveIngredient::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas('activeIngredients', fn (Builder $inner): Builder => $inner->whereKey($data['value']))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Sin categorías')
            ->emptyStateDescription('Precargue aquí las categorías con sus principios activos para luego armar los catálogos.');
    }
}
