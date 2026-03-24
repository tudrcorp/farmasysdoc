<?php

namespace App\Filament\Resources\PartnerCompanies\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PartnerCompanyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación de la compañía')
                    ->description('Datos legales y de convenio para identificar al aliado comercial.')
                    ->icon(Heroicon::BuildingOffice2)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Código')
                                    ->placeholder('—')
                                    ->badge()
                                    ->color('primary')
                                    ->copyable(),
                                TextEntry::make('legal_name')
                                    ->label('Razón social')
                                    ->placeholder('—')
                                    ->weight('medium')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextEntry::make('trade_name')
                                    ->label('Nombre comercial')
                                    ->placeholder('—'),
                                TextEntry::make('tax_id')
                                    ->label('NIT / ID fiscal')
                                    ->placeholder('—')
                                    ->copyable(),
                                IconEntry::make('is_active')
                                    ->label('Estado')
                                    ->boolean()
                                    ->trueIcon(Heroicon::CheckCircle)
                                    ->falseIcon(Heroicon::XCircle)
                                    ->trueColor('success')
                                    ->falseColor('danger'),
                                TextEntry::make('agreement_reference')
                                    ->label('Referencia de convenio')
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('agreement_terms')
                                    ->label('Términos del convenio')
                                    ->placeholder('—')
                                    ->columnSpanFull()
                                    ->prose()
                                    ->markdown(),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Contacto y ubicación')
                    ->description('Canales de comunicación y ubicación operativa.')
                    ->icon(Heroicon::MapPin)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('email')
                                    ->label('Correo corporativo')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->icon(Heroicon::Envelope)
                                    ->iconColor('gray'),
                                TextEntry::make('phone')
                                    ->label('Teléfono')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->icon(Heroicon::Phone)
                                    ->iconColor('gray'),
                                TextEntry::make('mobile_phone')
                                    ->label('Celular')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->icon(Heroicon::DevicePhoneMobile)
                                    ->iconColor('gray'),
                                TextEntry::make('website')
                                    ->label('Sitio web')
                                    ->placeholder('—')
                                    ->url(fn (?string $state): ?string => filled($state) ? $state : null)
                                    ->openUrlInNewTab()
                                    ->icon(Heroicon::GlobeAlt)
                                    ->iconColor('gray')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextEntry::make('address')
                                    ->label('Dirección')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('country')
                                    ->label('País')
                                    ->placeholder('—'),
                                TextEntry::make('state')
                                    ->label('Departamento / estado')
                                    ->placeholder('—'),
                                TextEntry::make('city')
                                    ->label('Ciudad')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Contacto comercial y trazabilidad')
                    ->description('Responsables de gestión y auditoría de cambios.')
                    ->icon(Heroicon::UserGroup)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('contact_name')
                                    ->label('Nombre contacto')
                                    ->placeholder('—'),
                                TextEntry::make('contact_email')
                                    ->label('Correo contacto')
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('contact_phone')
                                    ->label('Teléfono contacto')
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->placeholder('—'),
                                TextEntry::make('updated_by')
                                    ->label('Actualizado por')
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label('Fecha creación')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('notes')
                                    ->label('Notas internas')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
