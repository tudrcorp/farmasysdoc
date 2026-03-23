<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->searchable(),
                TextColumn::make('client.name')
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('convenio_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('convenio_partner_name')
                    ->searchable(),
                TextColumn::make('convenio_reference')
                    ->searchable(),
                TextColumn::make('delivery_recipient_name')
                    ->searchable(),
                TextColumn::make('delivery_phone')
                    ->searchable(),
                TextColumn::make('delivery_address')
                    ->searchable(),
                TextColumn::make('delivery_city')
                    ->searchable(),
                TextColumn::make('delivery_state')
                    ->searchable(),
                TextColumn::make('scheduled_delivery_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('dispatched_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('delivered_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('delivery_assignee')
                    ->searchable(),
                TextColumn::make('subtotal')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tax_total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('discount_total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_by')
                    ->searchable(),
                TextColumn::make('updated_by')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
