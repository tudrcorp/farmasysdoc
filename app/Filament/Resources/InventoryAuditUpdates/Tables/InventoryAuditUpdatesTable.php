<?php

namespace App\Filament\Resources\InventoryAuditUpdates\Tables;

use App\Support\Inventory\InventoryQuantityFormat;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryAuditUpdatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('processed_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('branch_name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product_sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('product_barcode')
                    ->label('Código')
                    ->searchable(),
                TextColumn::make('product_name')
                    ->label('Producto')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('previous_quantity')
                    ->label('Exist. ant.')
                    ->formatStateUsing(fn ($state): string => InventoryQuantityFormat::display($state)),
                TextColumn::make('new_quantity')
                    ->label('Exist. nueva')
                    ->formatStateUsing(fn ($state): string => InventoryQuantityFormat::display($state)),
                TextColumn::make('quantity_delta')
                    ->label('Delta')
                    ->formatStateUsing(fn ($state): string => InventoryQuantityFormat::display($state)),
                TextColumn::make('previous_cost_price')
                    ->label('Costo ant.')
                    ->money('USD'),
                TextColumn::make('new_cost_price')
                    ->label('Costo nuevo')
                    ->money('USD')
                    ->placeholder('—'),
                IconColumn::make('quantity_changed')
                    ->label('Qty')
                    ->boolean(),
                IconColumn::make('cost_changed')
                    ->label('Costo')
                    ->boolean(),
                TextColumn::make('processed_by_name')
                    ->label('Usuario')
                    ->toggleable(),
                TextColumn::make('inventory_audit_id')
                    ->label('Auditoría')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'),
                    )
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
