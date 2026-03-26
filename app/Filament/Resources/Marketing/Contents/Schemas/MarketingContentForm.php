<?php

namespace App\Filament\Resources\Marketing\Contents\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class MarketingContentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contenido promocional')
                    ->icon(Heroicon::Sparkles)
                    ->schema([
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                if (filled($get('slug'))) {
                                    return;
                                }
                                if (filled($state)) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Textarea::make('summary')
                            ->label('Resumen')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->label('Cuerpo')
                            ->rows(10)
                            ->columnSpanFull(),
                        Select::make('promo_type')
                            ->label('Tipo')
                            ->options([
                                'banner' => 'Banner',
                                'promo' => 'Promoción',
                                'landing' => 'Landing',
                                'newsletter' => 'Newsletter',
                            ])
                            ->native(false)
                            ->default('banner'),
                        TextInput::make('cta_label')
                            ->label('Texto del botón (CTA)'),
                        TextInput::make('cta_url')
                            ->label('URL del CTA')
                            ->url()
                            ->maxLength(2048),
                        TextInput::make('image_path')
                            ->label('Ruta o URL de imagen')
                            ->maxLength(2048)
                            ->columnSpanFull(),
                        Toggle::make('is_published')
                            ->label('Publicado')
                            ->default(false),
                        DateTimePicker::make('published_at')
                            ->label('Fecha de publicación')
                            ->native(false)
                            ->seconds(false),
                    ])
                    ->columns(2),
            ]);
    }
}
