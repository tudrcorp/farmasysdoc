<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Enums\PurchaseStatus;
use App\Models\Supplier;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación de la compra')
                    ->description('Número interno, proveedor y sucursal de recepción.')
                    ->icon(Heroicon::ClipboardDocumentCheck)
                    ->schema([
                        TextInput::make('purchase_number')
                            ->label('Número de orden de compra')
                            ->placeholder('Ej. OC-2026-0001')
                            ->helperText('Referencia única para trazabilidad con el proveedor y contabilidad.')
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
                                Select::make('supplier_id')
                                    ->label('Proveedor')
                                    ->relationship(
                                        name: 'supplier',
                                        titleAttribute: 'legal_name',
                                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('legal_name'),
                                    )
                                    ->getOptionLabelFromRecordUsing(
                                        fn (Supplier $record): string => $record->trade_name ?: $record->legal_name,
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->prefixIcon(Heroicon::Truck),
                                Select::make('branch_id')
                                    ->label('Sucursal de recepción')
                                    ->relationship(
                                        name: 'branch',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->helperText('Donde ingresa o registra la mercancía.')
                                    ->prefixIcon(Heroicon::BuildingStorefront),
                                Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        PurchaseStatus::Draft->value => 'Borrador',
                                        PurchaseStatus::Ordered->value => 'Pedido al proveedor',
                                        PurchaseStatus::PartiallyReceived->value => 'Recibido parcialmente',
                                        PurchaseStatus::Received->value => 'Recibido',
                                        PurchaseStatus::Cancelled->value => 'Cancelado',
                                    ])
                                    ->native(false)
                                    ->searchable()
                                    ->required()
                                    ->default(PurchaseStatus::Draft->value)
                                    ->prefixIcon(Heroicon::Signal),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Fechas del ciclo de compra')
                    ->description('Pedido al proveedor, entrega esperada y recepción final.')
                    ->icon(Heroicon::CalendarDays)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                DateTimePicker::make('ordered_at')
                                    ->label('Pedido enviado el')
                                    ->native(false)
                                    ->seconds(false)
                                    ->prefixIcon(Heroicon::PaperAirplane),
                                DateTimePicker::make('expected_delivery_at')
                                    ->label('Entrega estimada')
                                    ->native(false)
                                    ->seconds(false)
                                    ->prefixIcon(Heroicon::Clock),
                                DateTimePicker::make('received_at')
                                    ->label('Recepción completada')
                                    ->native(false)
                                    ->seconds(false)
                                    ->prefixIcon(Heroicon::CheckCircle),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Totales')
                    ->description('Montos del documento (coherentes con líneas de detalle e impuestos).')
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

                Section::make('Facturación y pago al proveedor')
                    ->description('Referencia de factura del proveedor y estado del pago.')
                    ->icon(Heroicon::DocumentCurrencyDollar)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('supplier_invoice_number')
                                    ->label('N° factura del proveedor')
                                    ->placeholder('Según documento recibido')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::DocumentText),
                                TextInput::make('payment_status')
                                    ->label('Estado del pago')
                                    ->placeholder('Ej. pendiente, parcial, pagado')
                                    ->maxLength(100)
                                    ->helperText('Texto libre o código interno de tesorería.')
                                    ->prefixIcon(Heroicon::CreditCard),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Notas internas')
                    ->description('Condiciones comerciales, incidencias o acuerdos con el proveedor.')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Observaciones')
                            ->placeholder('Lotes, garantías, diferencias en recepción…')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
