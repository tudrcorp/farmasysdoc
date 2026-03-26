<?php

namespace App\Filament\Resources\Marketing\Campaigns\Schemas;

use App\Enums\MarketingCampaignStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class MarketingCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campaña')
                    ->icon(Heroicon::Megaphone)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
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
                            ->unique(ignoreRecord: true)
                            ->helperText('URL amigable; se sugiere desde el nombre.'),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
                        Select::make('status')
                            ->label('Estado')
                            ->options(MarketingCampaignStatus::options())
                            ->native(false)
                            ->required()
                            ->default(MarketingCampaignStatus::Draft->value),
                        DateTimePicker::make('starts_at')
                            ->label('Inicio')
                            ->native(false)
                            ->seconds(false),
                        DateTimePicker::make('ends_at')
                            ->label('Fin')
                            ->native(false)
                            ->seconds(false),
                    ])
                    ->columns(2),
            ]);
    }
}
