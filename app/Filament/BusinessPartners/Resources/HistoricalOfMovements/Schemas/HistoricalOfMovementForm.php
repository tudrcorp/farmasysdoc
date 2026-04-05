<?php

namespace App\Filament\BusinessPartners\Resources\HistoricalOfMovements\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HistoricalOfMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->relationship('order', 'id')
                    ->required(),
                TextInput::make('total_quantity_products')
                    ->numeric(),
                TextInput::make('total_cost')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('remaining_credit')
                    ->numeric(),
            ]);
    }
}
