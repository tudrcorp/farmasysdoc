<?php

namespace App\Filament\BusinessPartners\Resources\HistoricalOfMovements\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HistoricalOfMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Nº pedido')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_quantity_products')
                    ->label('Cantidad total')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('total_cost')
                    ->label('Consumo (pedido)')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('remaining_credit')
                    ->label('Crédito disponible tras movimiento')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Última actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
