<?php

namespace App\Filament\Resources\PurchaseBooks\Schemas;

use App\Models\PurchaseBook;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PurchaseBookInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumen del comprobante')
                    ->description('Vista rápida de la retención asociada a esta operación.')
                    ->icon(Heroicon::ReceiptPercent)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('voucher_number')
                                    ->label('Nº comprobante')
                                    ->icon(Heroicon::Hashtag)
                                    ->weight('bold')
                                    ->fontFamily('mono')
                                    ->copyable()
                                    ->copyMessage('Comprobante copiado'),
                                TextEntry::make('tax_period')
                                    ->label('Periodo')
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('tax_retained_ves')
                                    ->label('Impuesto retenido')
                                    ->icon(Heroicon::Banknotes)
                                    ->color(fn ($state): string => (float) $state > 0 ? 'warning' : 'success')
                                    ->weight('bold')
                                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                                TextEntry::make('seniat_retention_percent')
                                    ->label('% SENIAT')
                                    ->badge()
                                    ->color(fn ($state): string => match (true) {
                                        $state === null => 'gray',
                                        (float) $state <= 0 => 'success',
                                        (float) $state >= 100 => 'danger',
                                        default => 'warning',
                                    })
                                    ->formatStateUsing(fn ($state): string => $state !== null
                                        ? number_format((float) $state, 2, ',', '.').' %'
                                        : '—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Agente de retención')
                    ->description('Datos fiscales de la empresa como agente de retención.')
                    ->icon(Heroicon::BuildingOffice2)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('retention_agent_name')
                                    ->label('Razón social')
                                    ->columnSpanFull()
                                    ->icon(Heroicon::BuildingOffice2),
                                TextEntry::make('retention_agent_rif')
                                    ->label('RIF')
                                    ->copyable()
                                    ->icon(Heroicon::Identification),
                                TextEntry::make('issue_date')
                                    ->label('Fecha de emisión')
                                    ->date('d/m/Y')
                                    ->placeholder('Pendiente / no emitido')
                                    ->icon(Heroicon::CalendarDays),
                                TextEntry::make('retention_agent_address')
                                    ->label('Dirección fiscal')
                                    ->columnSpanFull()
                                    ->icon(Heroicon::MapPin),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Proveedor')
                    ->description('Sujeto pasivo asociado a la compra.')
                    ->icon(Heroicon::Truck)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('supplier_name')
                                    ->label('Razón social')
                                    ->columnSpanFull()
                                    ->icon(Heroicon::Truck)
                                    ->weight('medium'),
                                TextEntry::make('supplier_rif')
                                    ->label('RIF')
                                    ->copyable()
                                    ->copyMessage('RIF copiado')
                                    ->icon(Heroicon::Identification)
                                    ->fontFamily('mono'),
                                TextEntry::make('supplier_address')
                                    ->label('Dirección fiscal')
                                    ->placeholder('Sin dirección registrada')
                                    ->columnSpanFull()
                                    ->icon(Heroicon::MapPin),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Documento y operación')
                    ->description('Factura o nota de débito y correlativos del periodo.')
                    ->icon(Heroicon::DocumentText)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->schema([
                                TextEntry::make('operation_number')
                                    ->label('Nº de operaciones')
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('operation_class')
                                    ->label('Clase de operación')
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('invoice_date')
                                    ->label('Fecha de la factura')
                                    ->date('d/m/Y')
                                    ->placeholder('—')
                                    ->icon(Heroicon::CalendarDays),
                                TextEntry::make('invoice_number')
                                    ->label('Nº factura / nota de débito')
                                    ->copyable()
                                    ->icon(Heroicon::DocumentText),
                                TextEntry::make('invoice_control_number')
                                    ->label('Nº de control')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->icon(Heroicon::QrCode),
                                TextEntry::make('affected_control_number')
                                    ->label('Nº control factura afectada')
                                    ->placeholder('No aplica')
                                    ->color('gray'),
                                TextEntry::make('purchase.purchase_number')
                                    ->label('Orden de compra interna')
                                    ->placeholder('—')
                                    ->icon(Heroicon::ShoppingCart),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Montos en bolívares')
                    ->description('Convertidos con la tasa BCV de la fecha de factura.')
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->schema([
                                TextEntry::make('invoice_total_ves')
                                    ->label('Total factura')
                                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state))
                                    ->icon(Heroicon::CurrencyDollar),
                                TextEntry::make('taxable_base_ves')
                                    ->label('Base imponible')
                                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state))
                                    ->icon(Heroicon::Scale),
                                TextEntry::make('vat_rate_percent')
                                    ->label('Alícuota')
                                    ->badge()
                                    ->color('info')
                                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' %'),
                                TextEntry::make('tax_caused_ves')
                                    ->label('Impuesto causado (IVA)')
                                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state))
                                    ->icon(Heroicon::ReceiptPercent),
                                TextEntry::make('tax_retained_ves')
                                    ->label('Impuesto retenido')
                                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state))
                                    ->weight('bold')
                                    ->color(fn ($state): string => (float) $state > 0 ? 'warning' : 'success')
                                    ->icon(Heroicon::Banknotes),
                                TextEntry::make('purchases_without_vat_credit')
                                    ->label('Compras sin derecho a crédito IVA')
                                    ->placeholder('No aplica')
                                    ->formatStateUsing(fn ($state): string => $state !== null
                                        ? self::formatBs((float) $state)
                                        : 'No aplica'),
                                TextEntry::make('bcv_rate_at_invoice')
                                    ->label('Tasa BCV aplicada')
                                    ->formatStateUsing(fn ($state): string => $state !== null
                                        ? number_format((float) $state, 6, ',', '.').' Bs/USD'
                                        : '—')
                                    ->icon(Heroicon::ArrowPath),
                                TextEntry::make('seniat_retention_percent')
                                    ->label('% retención (snapshot)')
                                    ->formatStateUsing(fn (PurchaseBook $record): string => $record->seniat_retention_percent !== null
                                        ? number_format((float) $record->seniat_retention_percent, 2, ',', '.').' %'
                                        : '—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->icon(Heroicon::Clock)
                    ->collapsed()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Registrado por')
                                    ->placeholder('—')
                                    ->icon(Heroicon::User),
                                TextEntry::make('created_at')
                                    ->label('Fecha de registro en sistema')
                                    ->dateTime('d/m/Y H:i:s')
                                    ->icon(Heroicon::Clock),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    private static function formatBs(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' Bs';
    }
}
