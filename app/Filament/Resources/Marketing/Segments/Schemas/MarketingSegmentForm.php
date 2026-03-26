<?php

namespace App\Filament\Resources\Marketing\Segments\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MarketingSegmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Segmento de clientes')
                    ->icon(Heroicon::Funnel)
                    ->description('Las reglas se evalúan al enviar una difusión en modo «Segmento».')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('rules_json')
                            ->label('Reglas (JSON)')
                            ->rows(8)
                            ->required()
                            ->helperText('Ejemplo: {"active_only":true,"has_email":true,"min_purchases":2,"min_lifetime_value":100}')
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                    ])
                    ->columns(1),
            ]);
    }
}
