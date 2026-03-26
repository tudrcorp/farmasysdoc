<?php

namespace App\Filament\Resources\Marketing\Broadcasts\Schemas;

use App\Enums\MarketingBroadcastSendMode;
use App\Enums\MarketingBroadcastStatus;
use App\Enums\MarketingBroadcastType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MarketingBroadcastInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumen')
                    ->icon(Heroicon::InformationCircle)
                    ->schema([
                        TextEntry::make('name')->label('Nombre'),
                        TextEntry::make('type')
                            ->label('Tipo')
                            ->formatStateUsing(fn ($state): string => MarketingBroadcastType::tryLabel($state)),
                        TextEntry::make('send_mode')
                            ->label('Audiencia')
                            ->formatStateUsing(fn ($state): string => MarketingBroadcastSendMode::tryLabel($state)),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->formatStateUsing(fn ($state): string => MarketingBroadcastStatus::tryLabel($state))
                            ->badge(),
                        TextEntry::make('campaign.name')
                            ->label('Campaña')
                            ->placeholder('—'),
                        TextEntry::make('segment.name')
                            ->label('Segmento')
                            ->placeholder('—'),
                        TextEntry::make('channels')
                            ->label('Canales')
                            ->formatStateUsing(function ($state): string {
                                if (! is_array($state)) {
                                    return '—';
                                }

                                return implode(', ', $state);
                            }),
                        TextEntry::make('scheduled_at')
                            ->label('Programado')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('started_at')
                            ->label('Inicio de envío')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('completed_at')
                            ->label('Fin de envío')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make('Mensaje')
                    ->icon(Heroicon::Envelope)
                    ->schema([
                        TextEntry::make('subject')->label('Asunto')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('email_html')
                            ->label('HTML')
                            ->html()
                            ->columnSpanFull(),
                        TextEntry::make('whatsapp_body')
                            ->label('WhatsApp')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
