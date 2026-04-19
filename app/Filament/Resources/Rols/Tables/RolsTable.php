<?php

namespace App\Filament\Resources\Rols\Tables;

use App\Models\Rol;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Rol')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->placeholder('—')
                    ->limit(80)
                    ->toggleable(),
                TextColumn::make('allowed_menu_items_count')
                    ->label('Ítems de menú')
                    ->state(function (Rol $record): int {
                        if ($record->allowed_menu_items === null) {
                            return count(User::defaultAllowedMenuItems());
                        }

                        return is_array($record->allowed_menu_items)
                            ? count($record->allowed_menu_items)
                            : 0;
                    })
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
