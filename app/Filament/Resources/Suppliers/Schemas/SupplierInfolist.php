<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SupplierInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación fiscal')
                    ->description('Datos legales y tributarios.')
                    ->icon(Heroicon::BuildingOffice2)
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
                                TextEntry::make('legal_name')
                                    ->label('Razón social')
                                    ->icon(Heroicon::DocumentText),
                                TextEntry::make('trade_name')
                                    ->label('Nombre comercial')
                                    ->placeholder('—')
                                    ->icon(Heroicon::BuildingStorefront),
                                TextEntry::make('tax_id')
                                    ->label('NIT / identificación fiscal')
                                    ->icon(Heroicon::Identification)
                                    ->copyable(),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Contacto de la empresa')
                    ->description('Medios generales del proveedor.')
                    ->icon(Heroicon::Phone)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('email')
                                    ->label('Correo electrónico')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Envelope)
                                    ->copyable(),
                                TextEntry::make('website')
                                    ->label('Sitio web')
                                    ->placeholder('—')
                                    ->icon(Heroicon::GlobeAlt)
                                    ->url(fn (?string $state): ?string => filled($state) ? $state : null)
                                    ->openUrlInNewTab(),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
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
                    ->description('Dirección registrada.')
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

                Section::make('Contacto comercial')
                    ->description('Persona o canal de seguimiento.')
                    ->icon(Heroicon::UserGroup)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('contact_name')
                                    ->label('Nombre del contacto')
                                    ->placeholder('—')
                                    ->icon(Heroicon::UserCircle),
                                TextEntry::make('contact_email')
                                    ->label('Correo del contacto')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Envelope)
                                    ->copyable(),
                                TextEntry::make('contact_phone')
                                    ->label('Teléfono del contacto')
                                    ->placeholder('—')
                                    ->icon(Heroicon::DevicePhoneMobile),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Condiciones comerciales')
                    ->description('Plazos y acuerdos acordados.')
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        TextEntry::make('payment_terms')
                            ->label('Términos de pago')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->prose(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Estado')
                    ->description('Visibilidad en órdenes de compra y catálogos.')
                    ->icon(Heroicon::Signal)
                    ->schema([
                        IconEntry::make('is_active')
                            ->label('Proveedor activo')
                            ->boolean(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Notas internas')
                    ->description('Observaciones del equipo.')
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
                    ->description('Trazabilidad del registro.')
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
