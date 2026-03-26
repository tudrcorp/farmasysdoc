<?php

namespace App\Filament\Resources\Marketing\Coupons\Tables;

use App\Enums\MarketingCouponDiscountType;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MarketingCouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('discount_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state): string => MarketingCouponDiscountType::tryLabel($state)),
                TextColumn::make('discount_value')
                    ->label('Valor')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('uses_count')
                    ->label('Usos')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('valid_until')
                    ->label('Vence')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
