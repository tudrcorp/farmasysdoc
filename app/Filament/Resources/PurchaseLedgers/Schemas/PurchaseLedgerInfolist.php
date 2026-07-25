<?php

namespace App\Filament\Resources\PurchaseLedgers\Schemas;

use App\Enums\PurchaseLedgerDocumentType;
use App\Models\PurchaseLedger;
use App\Support\Fiscal\VenezuelanRifFormatter;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PurchaseLedgerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Documento')
                    ->icon(Heroicon::DocumentText)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('operation_number')
                                    ->label('Número de operaciones'),
                                TextEntry::make('document_type')
                                    ->label('Tipo de documento')
                                    ->badge()
                                    ->formatStateUsing(fn (?PurchaseLedgerDocumentType $state): string => $state?->label() ?? '—'),
                                TextEntry::make('document_number')
                                    ->label('Factura o documento equivalente'),
                                TextEntry::make('control_number')
                                    ->label('Número de control')
                                    ->placeholder('—'),
                                TextEntry::make('tax_period')
                                    ->label('Periodo fiscal'),
                                TextEntry::make('invoice_date')
                                    ->label('Fecha de factura')
                                    ->date('d/m/Y')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Proveedor')
                    ->icon(Heroicon::Truck)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('supplier_name')
                                    ->label('Nombre completo / razón social'),
                                TextEntry::make('supplier_tax_id')
                                    ->label('Número de RIF o cédula')
                                    ->formatStateUsing(function (?string $state): string {
                                        $formatted = VenezuelanRifFormatter::format($state);

                                        return $formatted !== '' ? $formatted : '—';
                                    }),
                                TextEntry::make('taxpayer_type')
                                    ->label('Tipo de contribuyente')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Montos')
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('total_with_vat_and_exempt_ves')
                                    ->label('Total grabadas incluyendo IVA y exentas')
                                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                                TextEntry::make('exempt_amount_ves')
                                    ->label('Exentas, exoneradas o no sujetas')
                                    ->placeholder('—')
                                    ->formatStateUsing(fn ($state): string => $state !== null ? self::formatBs((float) $state) : '—'),
                                TextEntry::make('export_amount_ves')
                                    ->label('Monto exportación')
                                    ->placeholder('—'),
                                TextEntry::make('taxable_base_ves')
                                    ->label('Base imponible')
                                    ->formatStateUsing(fn ($state): string => $state !== null ? self::formatBs((float) $state) : '—'),
                                TextEntry::make('tax_caused_ves')
                                    ->label('IVA')
                                    ->formatStateUsing(fn ($state): string => $state !== null ? self::formatBs((float) $state) : '—'),
                                TextEntry::make('taxable_base_reduced_ves')
                                    ->label('Base imponible (columna adicional)')
                                    ->placeholder('—'),
                                TextEntry::make('tax_reduced_ves')
                                    ->label('IVA (columna adicional)')
                                    ->placeholder('—'),
                                TextEntry::make('vat_rate_percent')
                                    ->label('Alícuota')
                                    ->formatStateUsing(fn ($state): string => $state !== null
                                        ? number_format((float) $state, 0, ',', '.').'%'
                                        : '—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Retención IVA')
                    ->icon(Heroicon::ReceiptPercent)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('retention_voucher_issued_at')
                                    ->label('Fecha de emisión del comprobante de retención')
                                    ->date('d/m/Y')
                                    ->placeholder('Pendiente de impresión'),
                                TextEntry::make('retention_voucher_number')
                                    ->label('Número del comprobante de retención')
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('retention_amount_ves')
                                    ->label('Monto de retención efectuada')
                                    ->badge()
                                    ->color(fn ($state): string => (float) ($state ?? 0) > 0 ? 'warning' : 'gray')
                                    ->formatStateUsing(fn ($state): string => $state !== null
                                        ? self::formatBs((float) $state)
                                        : '—'),
                                TextEntry::make('purchaseBook.voucher_number')
                                    ->label('Retención vinculada')
                                    ->placeholder('—')
                                    ->visible(fn (PurchaseLedger $record): bool => $record->purchase_book_id !== null),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function formatBs(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' Bs';
    }
}
