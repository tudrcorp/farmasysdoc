<?php

namespace App\Filament\Resources\Marketing\UtmLinks\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MarketingUtmLinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('utm_campaign')
                    ->label('Campaña UTM')
                    ->placeholder('—'),
                TextColumn::make('full_url')
                    ->label('URL')
                    ->limit(48)
                    ->tooltip(fn ($record) => $record->full_url)
                    ->copyable(),
                TextColumn::make('clicks_count')
                    ->label('Clics')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
