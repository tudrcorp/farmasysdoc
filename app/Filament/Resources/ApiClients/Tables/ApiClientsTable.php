<?php

namespace App\Filament\Resources\ApiClients\Tables;

use App\Models\ApiClient;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApiClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Cliente')
                    ->description(fn (ApiClient $record): ?string => filled($record->allowed_ips) && is_array($record->allowed_ips) && $record->allowed_ips !== []
                        ? 'IPs: '.implode(', ', $record->allowed_ips)
                        : null)
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('token_hash')
                    ->label('Huella del token')
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? substr($state, 0, 10).'…'.substr($state, -6)
                        : '—')
                    ->copyable()
                    ->copyableState(fn (ApiClient $record): string => $record->token_hash ?? '')
                    ->tooltip('Hash SHA-256 (referencia). No es el secreto Bearer.')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('last_used_at')
                    ->label('Último uso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Alta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                //
            ])
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
