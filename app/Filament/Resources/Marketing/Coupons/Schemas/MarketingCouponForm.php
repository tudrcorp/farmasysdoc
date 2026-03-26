<?php

namespace App\Filament\Resources\Marketing\Coupons\Schemas;

use App\Enums\MarketingCouponDiscountType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MarketingCouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cupón / descuento')
                    ->icon(Heroicon::Ticket)
                    ->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->helperText('Ej. VERANO2026'),
                        TextInput::make('name')
                            ->label('Nombre interno')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(2)
                            ->columnSpanFull(),
                        Select::make('discount_type')
                            ->label('Tipo')
                            ->options(MarketingCouponDiscountType::options())
                            ->native(false)
                            ->required()
                            ->default(MarketingCouponDiscountType::Percent->value),
                        TextInput::make('discount_value')
                            ->label('Valor')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->helperText('Porcentaje o monto según el tipo.'),
                        TextInput::make('max_uses')
                            ->label('Máximo de usos')
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->helperText('Vacío = sin límite.'),
                        TextInput::make('uses_count')
                            ->label('Usos registrados')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        DateTimePicker::make('valid_from')
                            ->label('Válido desde')
                            ->native(false)
                            ->seconds(false),
                        DateTimePicker::make('valid_until')
                            ->label('Válido hasta')
                            ->native(false)
                            ->seconds(false),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
