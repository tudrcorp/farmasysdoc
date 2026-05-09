<?php

namespace App\Filament\Resources\FarmaExpressCostStructures\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FarmaExpressCostStructureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Estructura de costos FarmaExpress')
                    ->description('Define el porcentaje de ganancia por cada sucursal de tipo express.')
                    ->icon(Heroicon::Calculator)
                    ->schema([
                        Select::make('branch_id')
                            ->label('Sucursal express')
                            ->relationship(name: 'branch', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'unique' => 'Esta sucursal ya tiene una estructura de costos registrada.',
                            ]),
                        TextInput::make('profit_percentage')
                            ->label('Porcentaje de ganancia')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->step('0.01')
                            ->rule('decimal:0,2'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
