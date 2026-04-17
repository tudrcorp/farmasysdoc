<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Enums\SaleStatus;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Sale;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;

class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Documento y partes')
                    ->description('Identificación de la venta, sucursal y cliente.')
                    ->icon(Heroicon::ShoppingBag)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('sale_number')
                                    ->label('Número de venta')
                                    ->icon(Heroicon::Hashtag)
                                    ->weight('medium')
                                    ->copyable()
                                    ->copyMessage('Número copiado'),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->formatStateUsing(fn (?SaleStatus $state): string => $state instanceof SaleStatus ? $state->label() : '—')
                                    ->color(fn (?SaleStatus $state): string => match ($state) {
                                        SaleStatus::Draft => 'gray',
                                        SaleStatus::Completed => 'success',
                                        SaleStatus::Cancelled => 'danger',
                                        SaleStatus::Refunded => 'warning',
                                        default => 'gray',
                                    }),
                                TextEntry::make('branch_line')
                                    ->label('Sucursal')
                                    ->getStateUsing(function (Sale $record): ?string {
                                        $branch = $record->branch;
                                        if (! $branch) {
                                            return null;
                                        }

                                        $name = $branch->name ?? '';
                                        if (filled($branch->code)) {
                                            return $name.' · '.$branch->code;
                                        }

                                        return $name !== '' ? $name : null;
                                    })
                                    ->icon(Heroicon::BuildingStorefront)
                                    ->placeholder('Sin sucursal')
                                    ->url(fn (Sale $record): ?string => $record->branch_id
                                        ? BranchResource::getUrl('view', ['record' => $record->branch_id], isAbsolute: false)
                                        : null)
                                    ->openUrlInNewTab(false),
                                TextEntry::make('client.name')
                                    ->label('Cliente')
                                    ->icon(Heroicon::User)
                                    ->placeholder('Mostrador / sin cliente registrado')
                                    ->url(fn (Sale $record): ?string => $record->client_id
                                        ? ClientResource::getUrl('view', ['record' => $record->client_id], isAbsolute: false)
                                        : null)
                                    ->openUrlInNewTab(false),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Totales')
                    ->description('Montos del comprobante.')
                    ->icon(Heroicon::Calculator)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 5,
                        ])
                            ->schema([
                                TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money()
                                    ->icon(Heroicon::Banknotes),
                                TextEntry::make('tax_total')
                                    ->label('IVA')
                                    ->money()
                                    ->icon(Heroicon::ReceiptPercent),
                                TextEntry::make('igtf_total')
                                    ->label('IGTF')
                                    ->money()
                                    ->placeholder('—')
                                    ->icon(Heroicon::Banknotes),
                                TextEntry::make('discount_total')
                                    ->label('Descuentos')
                                    ->money()
                                    ->icon(Heroicon::Tag),
                                TextEntry::make('total')
                                    ->label('Total')
                                    ->money()
                                    ->weight('bold')
                                    ->icon(Heroicon::CurrencyDollar),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Cobro y fecha')
                    ->description('Medio de pago, estado del cobro y momento de la operación.')
                    ->icon(Heroicon::CreditCard)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('payment_method')
                                    ->label('Medio de pago')
                                    ->formatStateUsing(fn (?string $state): string => self::formatPaymentMethod($state))
                                    ->placeholder('—')
                                    ->icon(Heroicon::CreditCard),
                                TextEntry::make('payment_usd')
                                    ->label('Pago USD')
                                    ->money('USD')
                                    ->placeholder('—')
                                    ->icon(Heroicon::CurrencyDollar),
                                TextEntry::make('payment_ves')
                                    ->label('Pago VES')
                                    ->formatStateUsing(fn (?float $state): string => $state !== null
                                        ? 'Bs. '.number_format((float) $state, 2, ',', '.')
                                        : '—')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Banknotes),
                                TextEntry::make('bcv_ves_per_usd')
                                    ->label('Tasa BCV (Bs. / USD)')
                                    ->helperText('Valor usado al registrar el cobro en bolívares (API oficial o tasa manual en caja).')
                                    ->formatStateUsing(fn (?float $state): string => $state !== null && (float) $state > 0
                                        ? '1 USD = Bs. '.number_format((float) $state, 2, ',', '.')
                                        : '—')
                                    ->placeholder('—')
                                    ->icon(Heroicon::ChartBar),
                                TextEntry::make('reference')
                                    ->label('Referencia de pago')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->icon(Heroicon::Hashtag),
                                TextEntry::make('payment_status')
                                    ->label('Estado del cobro')
                                    ->formatStateUsing(fn (?string $state): string => self::formatPaymentStatus($state))
                                    ->badge()
                                    ->color(fn (?string $state): string => self::paymentStatusBadgeColor($state))
                                    ->placeholder('—')
                                    ->icon(Heroicon::CheckBadge),
                                TextEntry::make('sold_at')
                                    ->label('Fecha y hora de la venta')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->icon(Heroicon::CalendarDays)
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Detalle de ítems')
                    ->description('Productos incluidos en la venta con su detalle financiero por línea.')
                    ->icon(Heroicon::QueueList)
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('Ítems de la venta')
                            ->placeholder('No hay ítems asociados a esta venta.')
                            ->table([
                                TableColumn::make('Producto'),
                                TableColumn::make('Cant.')
                                    ->width('6rem')
                                    ->alignment(Alignment::Center),
                                TableColumn::make('P. unitario')
                                    ->alignment(Alignment::End),
                                TableColumn::make('Total línea')
                                    ->alignment(Alignment::End),
                            ])
                            ->schema([
                                TextEntry::make('product_name_snapshot')
                                    ->label('')
                                    ->formatStateUsing(function ($state, $record): string {
                                        $name = filled($state) ? (string) $state : 'Producto sin snapshot';
                                        $sku = (string) ($record->sku_snapshot ?? '—');

                                        return $name.' · SKU: '.$sku;
                                    })
                                    ->weight('medium'),
                                TextEntry::make('quantity')
                                    ->label('')
                                    ->alignment(Alignment::Center)
                                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0, '.', ',')),
                                TextEntry::make('unit_price')
                                    ->label('')
                                    ->money('USD'),
                                TextEntry::make('line_total')
                                    ->label('')
                                    ->money('USD')
                                    ->weight('medium'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Notas')
                    ->description('Observaciones internas asociadas a la venta.')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Observaciones')
                            ->placeholder('Sin notas')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->collapsed(),

                Section::make('Auditoría')
                    ->description('Trazabilidad del registro en el sistema.')
                    ->icon(Heroicon::Clock)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->placeholder('—')
                                    ->icon(Heroicon::UserCircle),
                                TextEntry::make('updated_by')
                                    ->label('Actualizado por')
                                    ->placeholder('—')
                                    ->icon(Heroicon::ArrowPath),
                                TextEntry::make('created_at')
                                    ->label('Registro creado')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon(Heroicon::CalendarDays),
                                TextEntry::make('updated_at')
                                    ->label('Última edición')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon(Heroicon::CalendarDays),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->collapsed(),
            ]);
    }

    private static function formatPaymentMethod(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        $key = strtolower(trim($value));

        return match ($key) {
            'efectivo_usd' => 'Efectivo USD',
            'efectivo_ves' => 'Efectivo VES',
            'transfer_ves' => 'Transferencia VES',
            'zelle' => 'Zelle',
            'pago_movil' => 'Pago Movil',
            'mixed' => 'Pago Multiple',
            'transfer_usd' => 'Transferencias USD',
            'traslado_sucursal' => 'Traslado entre sucursales (costo)',
            'cash', 'efectivo' => 'Efectivo',
            'card', 'tarjeta', 'debit', 'credit' => 'Tarjeta',
            'transfer', 'transferencia', 'nequi', 'daviplata' => 'Transferencia / digital',
            default => $value,
        };
    }

    private static function formatPaymentStatus(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        $key = strtolower(trim($value));

        return match ($key) {
            'paid', 'pagado', 'cobrado' => 'Pagado',
            'pending', 'pendiente' => 'Pendiente',
            'partial', 'parcial' => 'Parcial',
            'refunded', 'reembolsado' => 'Reembolsado',
            default => $value,
        };
    }

    private static function paymentStatusBadgeColor(?string $value): string
    {
        if (blank($value)) {
            return 'gray';
        }

        return match (strtolower(trim($value))) {
            'paid', 'pagado', 'cobrado' => 'success',
            'pending', 'pendiente' => 'warning',
            'partial', 'parcial' => 'info',
            'refunded', 'reembolsado' => 'danger',
            default => 'gray',
        };
    }
}
