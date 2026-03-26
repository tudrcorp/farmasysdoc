<?php

namespace App\Filament\Resources\Marketing\EmailTemplates\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MarketingEmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plantilla')
                    ->icon(Heroicon::EnvelopeOpen)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('subject')
                            ->label('Asunto del correo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('body_html')
                            ->label('Cuerpo HTML')
                            ->rows(14)
                            ->required()
                            ->helperText('Puede usar {{nombre}} y {{email}} como variables.')
                            ->columnSpanFull(),
                        Textarea::make('body_plain')
                            ->label('Texto plano (opcional)')
                            ->rows(4)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
