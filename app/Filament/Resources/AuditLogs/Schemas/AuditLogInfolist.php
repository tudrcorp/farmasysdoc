<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;

class AuditLogInfolist
{
    /**
     * Columnas JSON pueden llegar como array (modelo) o string (raw / sin cast).
     *
     * @return array<mixed>|null
     */
    private static function jsonColumnToArray(mixed $state): ?array
    {
        if ($state === null || $state === '') {
            return null;
        }

        if (is_array($state)) {
            return $state;
        }

        if (is_string($state)) {
            $decoded = json_decode($state, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Evento')
                    ->icon(Heroicon::Clock)
                    ->schema([
                        TextEntry::make('uid')
                            ->label('UID')
                            ->fontFamily(FontFamily::Mono)
                            ->placeholder('—'),
                        TextEntry::make('id')
                            ->label('ID'),
                        TextEntry::make('created_at')
                            ->label('Fecha y hora')
                            ->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('event')
                            ->label('Tipo de evento')
                            ->badge(),
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('Usuario')
                    ->icon(Heroicon::User)
                    ->schema([
                        TextEntry::make('user_id')
                            ->label('ID usuario')
                            ->placeholder('—'),
                        TextEntry::make('user_email')
                            ->label('Correo')
                            ->placeholder('—'),
                        TextEntry::make('roles_snapshot')
                            ->label('Roles (instantánea)')
                            ->formatStateUsing(function (mixed $state): string {
                                $roles = self::jsonColumnToArray($state);

                                return $roles !== null && $roles !== []
                                    ? json_encode($roles, JSON_UNESCAPED_UNICODE)
                                    : '—';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Entidad afectada')
                    ->icon(Heroicon::Cube)
                    ->schema([
                        TextEntry::make('auditable_type')
                            ->label('Tipo (clase)')
                            ->placeholder('—'),
                        TextEntry::make('auditable_id')
                            ->label('ID en base de datos')
                            ->placeholder('—'),
                        TextEntry::make('auditable_label')
                            ->label('Etiqueta legible')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Contexto HTTP')
                    ->icon(Heroicon::GlobeAlt)
                    ->schema([
                        TextEntry::make('panel_id')
                            ->label('Panel')
                            ->placeholder('—'),
                        TextEntry::make('http_method')
                            ->label('Método')
                            ->placeholder('—'),
                        TextEntry::make('route_name')
                            ->label('Nombre de ruta')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('url')
                            ->label('URL')
                            ->placeholder('—')
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('ip_address')
                            ->label('Dirección IP')
                            ->placeholder('—'),
                        TextEntry::make('user_agent')
                            ->label('User-Agent')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Propiedades / payload')
                    ->description('Cambios de datos, parámetros o metadatos capturados para esta traza.')
                    ->icon(Heroicon::DocumentText)
                    ->schema([
                        TextEntry::make('properties')
                            ->hiddenLabel()
                            ->formatStateUsing(function (mixed $state): string {
                                $props = self::jsonColumnToArray($state);
                                if ($props === null || $props === []) {
                                    return '—';
                                }

                                try {
                                    return json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                                } catch (\Throwable) {
                                    return '(No se pudo mostrar el JSON)';
                                }
                            })
                            ->fontFamily(FontFamily::Mono)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
