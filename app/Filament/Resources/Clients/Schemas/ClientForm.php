<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                                        'CC' => 'Cédula de Identidad',
                                        'CE' => 'Cédula Extranjero',
                                        'RIF' => 'Registro de Información Fiscal',
                                        'NIT' => 'Número de Identificación Tributaria',
                                        'PAS' => 'Pasaporte',
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
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::Home)
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                Select::make('country')
                                    ->label('País')
                                    ->default('Colombia')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->options(fn (): array => Country::query()
                                        ->orderBy('name')
                                        ->pluck('name', 'name')
                                        ->all())
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('state', null);
                                        $set('city', null);
                                    })
                                    ->prefixIcon(Heroicon::GlobeAlt),
                                Select::make('state')
                                    ->label('Departamento / estado')
                                    ->searchable()
                                    ->native(false)
                                    ->disabled(fn (Get $get): bool => blank($get('country')))
                                    ->options(function (Get $get): array {
                                        $countryName = $get('country');

                                        if (blank($countryName)) {
                                            return [];
                                        }

                                        return State::query()
                                            ->whereHas('country', fn ($query) => $query->where('name', $countryName))
                                            ->orderBy('name')
                                            ->pluck('name', 'name')
                                            ->all();
                                    })
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('city', null);
                                    })
                                    ->prefixIcon(Heroicon::Map),
                                Select::make('city')
                                    ->label('Ciudad')
                                    ->searchable()
                                    ->native(false)
                                    ->disabled(fn (Get $get): bool => blank($get('state')))
                                    ->options(function (Get $get): array {
                                        $stateName = $get('state');
                                        $countryName = $get('country');

                                        if (blank($stateName) || blank($countryName)) {
                                            return [];
                                        }

                                        return City::query()
                                            ->whereHas('country', fn ($query) => $query->where('name', $countryName))
                                            ->whereHas('state', fn ($query) => $query->where('name', $stateName))
                                            ->orderBy('name')
                                            ->pluck('name', 'name')
                                            ->all();
                                    })
                                    ->prefixIcon(Heroicon::BuildingLibrary),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Condiciones comerciales')
                    ->description('Descuento por cliente en porcentaje; el sistema puede usarlo al calcular ventas.')
                    ->icon(Heroicon::ReceiptPercent)
                    ->schema([
                        TextInput::make('customer_discount')
                            ->label('Descuento del cliente')
                            ->helperText('Porcentaje entre 0 y 100. Use 0 si este cliente no tiene descuento acordado.')
                            ->numeric()
                            ->inputMode('decimal')
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->step(0.01)
                            ->required()
                            ->prefixIcon(Heroicon::Tag),
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
