<?php

namespace App\Filament\Resources\Branches\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BranchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->description('Datos legales y comerciales de la sucursal.')
                    ->icon(Heroicon::BuildingStorefront)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Código interno')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Hashtag)
                                    ->copyable(),
                                TextEntry::make('name')
                                    ->label('Nombre comercial')
                                    ->icon(Heroicon::BuildingOffice2),
                                TextEntry::make('legal_name')
                                    ->label('Razón social')
                                    ->placeholder('—')
                                    ->icon(Heroicon::DocumentText),
                                TextEntry::make('tax_id')
                                    ->label('NIT / identificación fiscal')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Identification)
                                    ->copyable(),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Contacto')
                    ->description('Canales para clientes y proveedores.')
                    ->icon(Heroicon::Phone)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('email')
                                    ->label('Correo electrónico')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Envelope)
                                    ->copyable(),
                                TextEntry::make('phone')
                                    ->label('Teléfono fijo')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Phone),
                                TextEntry::make('mobile_phone')
                                    ->label('Celular / WhatsApp')
                                    ->placeholder('—')
                                    ->icon(Heroicon::DevicePhoneMobile),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Ubicación')
                    ->description('Dirección operativa y fiscal.')
                    ->icon(Heroicon::MapPin)
                    ->schema([
                        TextEntry::make('address')
                            ->label('Dirección')
                            ->placeholder('—')
                            ->icon(Heroicon::Home)
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                TextEntry::make('city')
                                    ->label('Ciudad')
                                    ->placeholder('—')
                                    ->icon(Heroicon::BuildingLibrary),
                                TextEntry::make('state')
                                    ->label('Departamento / estado')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Map),
                                TextEntry::make('country')
                                    ->label('País')
                                    ->icon(Heroicon::GlobeAlt),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Estado y operación')
                    ->description('Visibilidad en ventas y rol de sede.')
                    ->icon(Heroicon::Cog6Tooth)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                IconEntry::make('is_headquarters')
                                    ->label('Sede principal')
                                    ->boolean(),
                                IconEntry::make('is_active')
                                    ->label('Sucursal activa')
                                    ->boolean(),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Notas internas')
                    ->description('Solo para el equipo administrativo.')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Observaciones')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->prose(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->description('Trazabilidad del registro en el sistema.')
                    ->icon(Heroicon::Clock)
                    ->collapsed()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->placeholder('—')
                                    ->icon(Heroicon::User),
                                TextEntry::make('updated_by')
                                    ->label('Última modificación por')
                                    ->placeholder('—')
                                    ->icon(Heroicon::UserCircle),
                                TextEntry::make('created_at')
                                    ->label('Fecha de creación')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
