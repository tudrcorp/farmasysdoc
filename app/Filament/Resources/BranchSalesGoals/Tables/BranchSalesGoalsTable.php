<?php

namespace App\Filament\Resources\BranchSalesGoals\Tables;

use App\Models\BranchSalesGoal;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class BranchSalesGoalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period_label')
                    ->label('Periodo')
                    ->state(fn (BranchSalesGoal $record): string => $record->periodLabel())
                    ->sortable(query: function ($query, string $direction): void {
                        $query->orderBy('period_year', $direction)
                            ->orderBy('period_month', $direction);
                    })
                    ->searchable(query: function ($query, string $search): void {
                        $query->where(function ($query) use ($search): void {
                            $query->where('period_year', 'like', "%{$search}%")
                                ->orWhere('period_month', 'like', "%{$search}%");
                        });
                    }),
                IconColumn::make('is_global')
                    ->label('Global')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('scope_label')
                    ->label('Alcance')
                    ->state(fn (BranchSalesGoal $record): string => $record->scopeLabel())
                    ->description(fn (BranchSalesGoal $record): ?string => $record->is_global
                        ? 'Consolidado de todas las sucursales'
                        : 'Meta por sucursal'),
                TextColumn::make('goal_usd')
                    ->label('Meta USD')
                    ->formatStateUsing(fn (BranchSalesGoal $record): string => Number::currency(
                        (float) $record->goal_usd,
                        'USD',
                        'en',
                        2,
                    ))
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Actualizada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('period_year', 'desc')
            ->filters([
                SelectFilter::make('period_year')
                    ->label('Año')
                    ->options(BranchSalesGoal::yearOptions()),
                SelectFilter::make('period_month')
                    ->label('Mes')
                    ->options(BranchSalesGoal::monthOptions()),
                TernaryFilter::make('is_global')
                    ->label('Alcance')
                    ->placeholder('Todas')
                    ->trueLabel('Solo global')
                    ->falseLabel('Solo por sucursal'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
