<?php

namespace App\Filament\Resources\Marketing\UtmLinks\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MarketingUtmLinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Enlace con parámetros UTM')
                    ->icon(Heroicon::Link)
                    ->description('La URL final se calcula al guardar.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre interno')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('base_url')
                            ->label('URL base')
                            ->required()
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make('utm_source')
                            ->label('utm_source')
                            ->maxLength(255),
                        TextInput::make('utm_medium')
                            ->label('utm_medium')
                            ->maxLength(255),
                        TextInput::make('utm_campaign')
                            ->label('utm_campaign')
                            ->maxLength(255),
                        TextInput::make('utm_content')
                            ->label('utm_content')
                            ->maxLength(255),
                        TextInput::make('utm_term')
                            ->label('utm_term')
                            ->maxLength(255),
                        Textarea::make('full_url')
                            ->label('URL generada')
                            ->rows(2)
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
