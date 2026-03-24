<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación fiscal')
                    ->description('Razón social, nombre comercial y datos tributarios del proveedor.')
                    ->icon(Heroicon::BuildingOffice2)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextInput::make('code')
                                    ->label('Código interno')
                                    ->disabled()
                                    ->placeholder('Se asigna al guardar')
                                    ->helperText('Se genera automáticamente al crear el registro: PROV- + id del proveedor con 4 dígitos (ej. PROV-0001, PROV-0042). No se puede editar.')
                                    ->prefixIcon(Heroicon::Hashtag)
                                    ->dehydrated(false),
                                TextInput::make('legal_name')
                                    ->label('Razón social')
                                    ->placeholder('Según cámara de comercio o RUT')
                                    ->helperText('Nombre legal para órdenes de compra y facturación.')
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::DocumentText)
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextInput::make('trade_name')
                                    ->label('Nombre comercial')
                                    ->placeholder('Marca o nombre en factura del proveedor')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::BuildingStorefront),
                                TextInput::make('tax_id')
                                    ->label('NIT / identificación fiscal')
                                    ->placeholder('Número sin guiones o con formato local')
                                    ->helperText('Solo letras mayúsculas y números, sin espacios ni caracteres especiales.')
                                    ->required()
                                    ->rule('regex:/^[A-Z0-9]+$/')
                                    ->validationMessages([
                                        'regex' => 'El NIT / identificación fiscal solo puede contener letras mayúsculas (A-Z) y números, sin espacios ni símbolos.',
                                    ])
                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                        ? strtoupper(str_replace(' ', '', $state))
                                        : null)
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::Identification),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Contacto de la empresa')
                    ->description('Canales generales del laboratorio o distribuidor.')
                    ->icon(Heroicon::Phone)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 2,
                        ])
                            ->schema([
                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->placeholder('compras@proveedor.com')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::Envelope)
                                    ->autocomplete('email'),
                                TextInput::make('website')
                                    ->label('Sitio web')
                                    ->url()
                                    ->placeholder('https://')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::GlobeAlt)
                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state === '' || $state === null ? null : $state),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('phone')
                                    ->label('Teléfono fijo')
                                    ->tel()
                                    ->placeholder('Ej. 601 123 4567')
                                    ->helperText('Solo números, sin espacios ni caracteres especiales.')
                                    ->rule('regex:/^[0-9]+$/')
                                    ->validationMessages([
                                        'regex' => 'El teléfono fijo solo puede contener números, sin espacios ni caracteres especiales.',
                                    ])
                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                        ? preg_replace('/[^0-9]/', '', $state)
                                        : null)
                                    ->maxLength(40)
                                    ->prefixIcon(Heroicon::Phone),
                                TextInput::make('mobile_phone')
                                    ->label('Celular / WhatsApp')
                                    ->tel()
                                    ->placeholder('Ej. 300 123 4567')
                                    ->helperText('Solo números, sin espacios ni caracteres especiales.')
                                    ->rule('regex:/^[0-9]+$/')
                                    ->validationMessages([
                                        'regex' => 'El celular solo puede contener números, sin espacios ni caracteres especiales.',
                                    ])
                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                        ? preg_replace('/[^0-9]/', '', $state)
                                        : null)
                                    ->maxLength(40)
                                    ->prefixIcon(Heroicon::DevicePhoneMobile),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Ubicación')
                    ->description('Dirección fiscal o de correspondencia.')
                    ->icon(Heroicon::MapPin)
                    ->schema([
                        TextInput::make('address')
                            ->label('Dirección')
                            ->placeholder('Calle, número, barrio, ciudad')
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
                                    ->required()
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

                Section::make('Contacto comercial')
                    ->description('Persona o área para pedidos, cotizaciones y seguimiento.')
                    ->icon(Heroicon::UserGroup)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('contact_name')
                                    ->label('Nombre del contacto')
                                    ->placeholder('Ej. María López — Compras')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::UserCircle),
                                TextInput::make('contact_email')
                                    ->label('Correo del contacto')
                                    ->email()
                                    ->placeholder('contacto@proveedor.com')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::Envelope),
                                TextInput::make('contact_phone')
                                    ->label('Teléfono del contacto')
                                    ->tel()
                                    ->placeholder('Extensión o celular directo')
                                    ->maxLength(40)
                                    ->prefixIcon(Heroicon::DevicePhoneMobile),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Condiciones comerciales')
                    ->description('Plazos de pago y acuerdos habituales con este proveedor.')
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        Textarea::make('payment_terms')
                            ->label('Términos de pago')
                            ->placeholder('Ej. contado, 30 días fecha factura, descuento pronto pago…')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Estado')
                    ->description('Los proveedores inactivos no deberían aparecer en nuevas órdenes de compra.')
                    ->icon(Heroicon::Cog6Tooth)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Proveedor activo')
                            ->helperText('Desactívalo si ya no compras a este laboratorio o distribuidor.')
                            ->inline(false)
                            ->default(true),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Notas internas')
                    ->description('Solo para el equipo administrativo y compras.')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Observaciones')
                            ->placeholder('Acuerdos especiales, incidencias de calidad, tiempos de entrega…')
                            ->rows(4)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
