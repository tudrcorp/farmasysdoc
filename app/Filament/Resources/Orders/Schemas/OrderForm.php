<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\ConvenioType;
use App\Enums\OrderStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del pedido')
                    ->description('Identificación del pedido, cliente, sucursal y estado en el flujo de entrega.')
                    ->icon(Heroicon::ShoppingCart)
                    ->schema([
                        TextInput::make('order_number')
                            ->label('Número de pedido')
                            ->placeholder('Ej. PED-2026-0001')
                            ->helperText('Referencia visible para el cliente y seguimiento. Debe ser única.')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->prefixIcon(Heroicon::Hashtag)
                            ->autocomplete('off')
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('client_id')
                                    ->label('Cliente')
                                    ->relationship(
                                        name: 'client',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query) => $query->where('status', 'active')->orderBy('name'),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->prefixIcon(Heroicon::User),
                                Select::make('branch_id')
                                    ->label('Sucursal')
                                    ->relationship(
                                        name: 'branch',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('Sin sucursal asignada')
                                    ->helperText('Opcional. Sucursal que prepara o despacha el pedido.')
                                    ->prefixIcon(Heroicon::BuildingStorefront),
                                Select::make('status')
                                    ->label('Estado del pedido')
                                    ->options([
                                        OrderStatus::Pending->value => 'Pendiente',
                                        OrderStatus::Confirmed->value => 'Confirmado',
                                        OrderStatus::Preparing->value => 'En preparación',
                                        OrderStatus::ReadyForDispatch->value => 'Listo para despacho',
                                        OrderStatus::Dispatched->value => 'Despachado',
                                        OrderStatus::InTransit->value => 'En tránsito',
                                        OrderStatus::Delivered->value => 'Entregado',
                                        OrderStatus::Cancelled->value => 'Cancelado',
                                    ])
                                    ->native(false)
                                    ->searchable()
                                    ->required()
                                    ->default(OrderStatus::Pending->value)
                                    ->prefixIcon(Heroicon::Signal),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Convenio y cobertura')
                    ->description('Datos de EPS, aseguradora o empresa cuando el pedido no es particular.')
                    ->icon(Heroicon::ShieldCheck)
                    ->schema([
                        Select::make('convenio_type')
                            ->label('Tipo de convenio')
                            ->options([
                                ConvenioType::Particular->value => 'Particular',
                                ConvenioType::PrivateInsurance->value => 'Seguro privado',
                                ConvenioType::Eps->value => 'EPS',
                                ConvenioType::PrepaidMedicine->value => 'Medicina prepagada',
                                ConvenioType::Corporate->value => 'Convenio corporativo',
                                ConvenioType::Other->value => 'Otro',
                            ])
                            ->native(false)
                            ->searchable()
                            ->required()
                            ->default(ConvenioType::Particular->value)
                            ->helperText('Determina qué datos adicionales suelen exigir autorización o facturación.')
                            ->prefixIcon(Heroicon::BuildingLibrary),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('convenio_partner_name')
                                    ->label('Aliado / entidad')
                                    ->placeholder('Nombre de EPS, aseguradora o empresa')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::BuildingOffice2),
                                TextInput::make('convenio_reference')
                                    ->label('Referencia del convenio')
                                    ->placeholder('Autorización, póliza, código de afiliación…')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::DocumentText),
                            ]),
                        Textarea::make('convenio_notes')
                            ->label('Notas del convenio')
                            ->placeholder('Coberturas, copagos, límites u observaciones para facturación.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Entrega')
                    ->description('Contacto, dirección y seguimiento logístico del envío.')
                    ->icon(Heroicon::Truck)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('delivery_recipient_name')
                                    ->label('Quien recibe')
                                    ->placeholder('Nombre completo')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::UserCircle),
                                TextInput::make('delivery_phone')
                                    ->label('Teléfono de contacto')
                                    ->tel()
                                    ->placeholder('Ej. 300 123 4567')
                                    ->maxLength(40)
                                    ->prefixIcon(Heroicon::Phone),
                            ]),
                        TextInput::make('delivery_address')
                            ->label('Dirección de entrega')
                            ->placeholder('Calle, número, barrio, torre/apto')
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::Home)
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('delivery_city')
                                    ->label('Ciudad')
                                    ->maxLength(100)
                                    ->prefixIcon(Heroicon::MapPin),
                                TextInput::make('delivery_state')
                                    ->label('Departamento / estado')
                                    ->maxLength(100)
                                    ->prefixIcon(Heroicon::Map),
                            ]),
                        Textarea::make('delivery_notes')
                            ->label('Indicaciones para entrega')
                            ->placeholder('Horario, portería, punto de referencia…')
                            ->rows(3)
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                DateTimePicker::make('scheduled_delivery_at')
                                    ->label('Entrega programada')
                                    ->native(false)
                                    ->seconds(false)
                                    ->prefixIcon(Heroicon::CalendarDays),
                                DateTimePicker::make('dispatched_at')
                                    ->label('Despachado el')
                                    ->native(false)
                                    ->seconds(false)
                                    ->prefixIcon(Heroicon::ArrowRightCircle),
                                DateTimePicker::make('delivered_at')
                                    ->label('Entregado el')
                                    ->native(false)
                                    ->seconds(false)
                                    ->prefixIcon(Heroicon::CheckCircle),
                            ]),
                        TextInput::make('delivery_assignee')
                            ->label('Responsable de entrega')
                            ->placeholder('Mensajero, transportadora o equipo interno')
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::UserGroup)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Totales')
                    ->description('Montos del documento (suelen alimentarse desde las líneas del pedido).')
                    ->icon(Heroicon::Calculator)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 4,
                        ])
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->default(0.0)
                                    ->prefix('$'),
                                TextInput::make('tax_total')
                                    ->label('Impuestos')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->default(0.0)
                                    ->prefix('$'),
                                TextInput::make('discount_total')
                                    ->label('Descuentos')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->default(0.0)
                                    ->prefix('$'),
                                TextInput::make('total')
                                    ->label('Total')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->default(0.0)
                                    ->prefix('$'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Notas internas')
                    ->description('Observaciones solo para el equipo (no suelen mostrarse al cliente).')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Observaciones')
                            ->placeholder('Incidencias, acuerdos comerciales, llamadas…')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
