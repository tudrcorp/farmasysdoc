<?php

namespace App\Filament\Resources\FarmaExpressCostStructures\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FarmaExpressCostStructureInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Estructura de costos FarmaExpress')
                    ->description('Porcentaje de ganancia configurado por sucursal express.')
                    ->icon(Heroicon::Calculator)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('branch.name')
                                    ->label('Sucursal express')
                                    ->icon(Heroicon::BuildingStorefront)
                                    ->placeholder('—'),
                                TextEntry::make('profit_percentage')
                                    ->label('Porcentaje de ganancia')
                                    ->numeric(decimalPlaces: 2)
                                    ->suffix('%')
                                    ->icon(Heroicon::CurrencyDollar),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
