<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier.id')
                    ->searchable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('barcode')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('product_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('brand')
                    ->searchable(),
                TextColumn::make('presentation')
                    ->searchable(),
                TextColumn::make('unit_of_measure')
                    ->searchable(),
                TextColumn::make('unit_content')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('net_content_label')
                    ->searchable(),
                TextColumn::make('sale_price')
                    ->money()
                    ->sortable(),
                TextColumn::make('cost_price')
                    ->money()
                    ->sortable(),
                TextColumn::make('tax_rate')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('concentration')
                    ->searchable(),
                TextColumn::make('presentation_type')
                    ->searchable(),
                IconColumn::make('requires_prescription')
                    ->boolean(),
                IconColumn::make('is_controlled_substance')
                    ->boolean(),
                TextColumn::make('health_registration_number')
                    ->searchable(),
                TextColumn::make('manufacturer')
                    ->searchable(),
                TextColumn::make('model')
                    ->searchable(),
                TextColumn::make('warranty_months')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('medical_device_class')
                    ->searchable(),
                IconColumn::make('requires_calibration')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->boolean(),
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
