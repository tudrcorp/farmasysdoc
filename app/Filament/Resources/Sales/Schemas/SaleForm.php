<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Enums\SaleStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la venta')
                    ->description('Número de ticket o factura, sucursal, cliente y estado.')
                    ->icon(Heroicon::ShoppingBag)
                    ->schema([
                        TextInput::make('sale_number')
                            ->label('Número de venta')
                            ->placeholder('Ej. VTA-2026-0001 o prefijo de POS')
                            ->helperText('Referencia única para caja, cliente y contabilidad.')
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
                                    ->required()
                                    ->prefixIcon(Heroicon::BuildingStorefront),
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
                                    ->placeholder('Venta sin cliente registrado')
                                    ->helperText('Opcional para mostrador o ventas informales.')
                                    ->prefixIcon(Heroicon::User),
                                Select::make('status')
                                    ->label('Estado')
                                    ->options(SaleStatus::options())
                                    ->native(false)
                                    ->searchable()
                                    ->required()
                                    ->default(SaleStatus::Draft->value)
                                    ->prefixIcon(Heroicon::Signal),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Totales')
                    ->description('Montos del documento (alineados con líneas de detalle y descuentos).')
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

                Section::make('Cobro')
                    ->description('Medio de pago y estado del cobro.')
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('payment_method')
                                    ->label('Medio de pago')
                                    ->placeholder('Efectivo, tarjeta, transferencia, mixto…')
                                    ->maxLength(100)
                                    ->prefixIcon(Heroicon::CreditCard),
                                TextInput::make('payment_status')
                                    ->label('Estado del cobro')
                                    ->placeholder('Ej. pagado, parcial, pendiente')
                                    ->maxLength(100)
                                    ->helperText('Coherente con políticas de caja y cartera.')
                                    ->prefixIcon(Heroicon::CheckBadge),
                                TextInput::make('bcv_ves_per_usd')
                                    ->label('Tasa BCV (Bs. por 1 USD)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.000001)
                                    ->placeholder('Solo si hubo cobro en bolívares')
                                    ->helperText('Opcional. Se guarda automáticamente desde el POS; use este campo para ajustes o ventas manuales.')
                                    ->prefixIcon(Heroicon::ChartBar),
                            ]),
                        DateTimePicker::make('sold_at')
                            ->label('Fecha y hora de la venta')
                            ->native(false)
                            ->seconds(false)
                            ->helperText('Momento en que se concretó el cobro o se cerró el ticket.')
                            ->prefixIcon(Heroicon::CalendarDays)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Notas internas')
                    ->description('Observaciones para el equipo (devoluciones, promociones, incidencias).')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Observaciones')
                            ->placeholder('Detalle que deba quedar registrado en auditoría.')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
