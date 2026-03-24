<?php

namespace App\Filament\Resources\Branches\Schemas;

use App\Models\Branch;
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

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->description('Datos que identifican la sucursal ante clientes y entidades.')
                    ->icon(Heroicon::BuildingStorefront)
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'sm' => 2,
                                    'lg' => 3,
                                ])
                                    ->schema([
                                        TextInput::make('code')
                                            ->label('Código interno')
                                            ->helperText('Se genera automáticamente al guardar: SUC- más el ID del registro (no editable).')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->placeholder('Se asignará al guardar')
                                            ->formatStateUsing(function (?string $state, $record): string {
                                                if ($record instanceof Branch && $record->getKey()) {
                                                    return 'SUC-000'.$record->getKey();
                                                }

                                                return '';
                                            })
                                            ->prefixIcon(Heroicon::Hashtag)
                                            ->autocomplete('off')
                                            ->columnSpan(['default' => 1, 'lg' => 1]),
                                        TextInput::make('name')
                                            ->label('Nombre comercial')
                                            ->placeholder('Nombre en mostrador o factura')
                                            ->helperText('Nombre visible para el equipo y en listados.')
                                            ->required()
                                            ->maxLength(255)
                                            ->prefixIcon(Heroicon::BuildingOffice2)
                                            ->columnSpan(['default' => 1, 'lg' => 2]),
                                    ]),
                                TextInput::make('legal_name')
                                    ->label('Razón social')
                                    ->placeholder('Opcional, si difiere del nombre comercial')
                                    ->helperText('Solo si la sucursal factura con razón social distinta.')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::DocumentText)
                                    ->columnSpanFull(),
                                TextInput::make('tax_id')
                                    ->label('NIT / identificación fiscal')
                                    ->placeholder('Número sin guiones o con formato local')
                                    ->helperText('Solo letras mayúsculas y números, sin espacios ni caracteres especiales.')
                                    ->rule('regex:/^[A-Z0-9]+$/')
                                    ->validationMessages([
                                        'regex' => 'El NIT / identificación fiscal solo puede contener letras mayúsculas (A-Z) y números, sin espacios ni símbolos.',
                                    ])
                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                        ? strtoupper(str_replace(' ', '', $state))
                                        : null)
                                    ->maxLength(50)
                                    ->prefixIcon(Heroicon::Identification)
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Contacto')
                    ->description('Medios para comunicar pedidos, citas o incidencias.')
                    ->icon(Heroicon::Phone)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->placeholder('sucursal@empresa.com')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::Envelope)
                                    ->autocomplete('email'),
                                TextInput::make('phone')
                                    ->label('Teléfono fijo')
                                    ->tel()
                                    ->placeholder('Ej. 601 123 4567')
                                    ->maxLength(40)
                                    ->prefixIcon(Heroicon::Phone),
                                TextInput::make('mobile_phone')
                                    ->label('Celular / WhatsApp')
                                    ->tel()
                                    ->placeholder('Ej. 300 123 4567')
                                    ->maxLength(40)
                                    ->prefixIcon(Heroicon::DevicePhoneMobile),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Ubicación')
                    ->description('Dirección física para envíos, visitas o logística.')
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
                                    ->required()
                                    ->default('Venezuela')
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

                Section::make('Estado y operación')
                    ->description('Controla si la sucursal factura y si es la sede principal.')
                    ->icon(Heroicon::Cog6Tooth)
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                Toggle::make('is_headquarters')
                                    ->label('Sede principal')
                                    ->helperText('Marca solo una sucursal como sede principal de la empresa.')
                                    ->inline(false)
                                    ->default(false),
                                Toggle::make('is_active')
                                    ->label('Sucursal activa')
                                    ->helperText('Si está desactivada, no debería usarse en ventas ni pedidos nuevos.')
                                    ->inline(false)
                                    ->default(true),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Notas internas')
                    ->description('Información solo para el equipo administrativo.')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Observaciones')
                            ->placeholder('Horarios especiales, contactos internos, acuerdos con el centro comercial…')
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
