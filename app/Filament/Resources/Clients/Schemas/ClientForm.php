<?php

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identidad y documento')
                    ->description('Datos legales y de identificación del cliente o empresa.')
                    ->icon(Heroicon::User)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre completo o razón social')
                            ->placeholder('Ej. Juan Pérez o Droguería del Centro S.A.S.')
                            ->helperText('Como aparecerá en facturas y pedidos.')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::UserCircle)
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                Select::make('document_type')
                                    ->label('Tipo de documento')
                                    ->options([
                                        'CC' => 'Cédula de ciudadanía',
                                        'CE' => 'Cédula de extranjería',
                                        'NIT' => 'NIT',
                                        'PAS' => 'Pasaporte',
                                        'TI' => 'Tarjeta de identidad',
                                        'RUT' => 'RUT',
                                        'OTRO' => 'Otro',
                                    ])
                                    ->native(false)
                                    ->searchable()
                                    ->required()
                                    ->prefixIcon(Heroicon::Identification),
                                TextInput::make('document_number')
                                    ->label('Número de documento')
                                    ->placeholder('Sin puntos o con formato que maneje su proceso')
                                    ->helperText('Debe coincidir con el documento presentado en compras con convenio.')
                                    ->required()
                                    ->maxLength(50)
                                    ->prefixIcon(Heroicon::Hashtag),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Contacto')
                    ->description('Medios para notificaciones, entregas y facturación electrónica.')
                    ->icon(Heroicon::Phone)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::Envelope)
                                    ->autocomplete('email')
                                    ->helperText('Debe ser único en el sistema.'),
                                TextInput::make('phone')
                                    ->label('Teléfono principal')
                                    ->tel()
                                    ->required()
                                    ->maxLength(40)
                                    ->placeholder('Ej. 300 123 4567')
                                    ->prefixIcon(Heroicon::DevicePhoneMobile),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Ubicación')
                    ->description('Dirección de residencia, fiscal o de entrega preferida.')
                    ->icon(Heroicon::MapPin)
                    ->schema([
                        TextInput::make('address')
                            ->label('Dirección')
                            ->placeholder('Calle, número, barrio, referencia')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::Home)
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextInput::make('city')
                                    ->label('Ciudad')
                                    ->required()
                                    ->maxLength(100)
                                    ->prefixIcon(Heroicon::BuildingLibrary),
                                TextInput::make('state')
                                    ->label('Departamento / estado')
                                    ->required()
                                    ->maxLength(100)
                                    ->prefixIcon(Heroicon::Map),
                                TextInput::make('country')
                                    ->label('País')
                                    ->required()
                                    ->default('Colombia')
                                    ->maxLength(100)
                                    ->prefixIcon(Heroicon::GlobeAlt),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Estado del cliente')
                    ->description('Controla si puede comprar o recibir pedidos en el sistema.')
                    ->icon(Heroicon::ShieldCheck)
                    ->schema([
                        Select::make('status')
                            ->label('Estado')
                            ->options([
                                'active' => 'Activo',
                                'inactive' => 'Inactivo',
                                'blocked' => 'Bloqueado',
                            ])
                            ->native(false)
                            ->required()
                            ->default('active')
                            ->helperText('Los clientes bloqueados o inactivos pueden ocultarse en caja y pedidos.')
                            ->prefixIcon(Heroicon::Signal),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
