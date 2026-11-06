<?php

namespace App\Filament\Resources\FiscalZReports\Schemas;

use App\Enums\FiscalZReportSource;
use App\Models\FiscalZReport;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class FiscalZReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Reporte')
                    ->icon(Heroicon::LockClosed)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('z_number')
                                    ->label('Z Nº')
                                    ->formatStateUsing(fn (int $state): string => str_pad((string) $state, 4, '0', STR_PAD_LEFT))
                                    ->badge()
                                    ->size(TextSize::Large),
                                TextEntry::make('report_datetime')->label('Fecha y hora')->dateTime('d/m/Y h:i A'),
                                TextEntry::make('branch.name')->label('Sucursal'),
                                TextEntry::make('fiscalPrinter.name')->label('Máquina fiscal'),
                                TextEntry::make('printer_serial')->label('Serial')->copyable(),
                                TextEntry::make('sales_total')
                                    ->label('Total ventas (Bs)')
                                    ->state(fn (FiscalZReport $record): float => $record->salesTotalVes())
                                    ->numeric(2)
                                    ->weight(FontWeight::Bold)
                                    ->size(TextSize::Large),
                                TextEntry::make('source')
                                    ->label('Origen')
                                    ->formatStateUsing(fn (FiscalZReportSource $state): string => $state->label())
                                    ->badge()
                                    ->color('gray'),
                                IconEntry::make('integrity')
                                    ->label('Íntegro')
                                    ->state(fn (FiscalZReport $record): bool => $record->hasValidContentHash())
                                    ->boolean()
                                    ->hint(fn (FiscalZReport $record): string => $record->hasValidContentHash()
                                        ? 'Sin cambios desde la captura'
                                        : 'Los datos cambiaron después de la captura'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Numeración')
                    ->icon(Heroicon::Hashtag)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('last_invoice_number')->label('Última factura')->formatStateUsing(fn (?int $state): string => self::pad($state, 8))
                                    ->hint(fn (FiscalZReport $record): ?string => $record->last_invoice_datetime?->format('d/m/Y h:i A')),
                                TextEntry::make('last_credit_note_number')->label('Última nota de crédito')->formatStateUsing(fn (?int $state): string => self::pad($state, 8))->placeholder('—'),
                                TextEntry::make('last_debit_note_number')->label('Última nota de débito')->formatStateUsing(fn (?int $state): string => self::pad($state, 8))->placeholder('—'),
                                TextEntry::make('last_non_fiscal_number')->label('Último no fiscal')->formatStateUsing(fn (?int $state): string => self::pad($state, 8))->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                self::amountsSection('Ventas', 'sales', Heroicon::ShoppingCart, 'success'),
                self::amountsSection('Notas de crédito', 'credit_notes', Heroicon::ReceiptRefund, 'danger'),
                self::amountsSection('Notas de débito', 'debit_notes', Heroicon::DocumentPlus, 'warning'),

                Section::make('Alícuotas programadas')
                    ->icon(Heroicon::Calculator)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(['default' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('general_rate_percent')->label('General')->suffix('%')->placeholder('—'),
                                TextEntry::make('reduced_rate_percent')->label('Reducida')->suffix('%')->placeholder('—'),
                                TextEntry::make('additional_rate_percent')->label('Adicional')->suffix('%')->placeholder('—'),
                                TextEntry::make('igtf_rate_percent')->label('IGTF')->suffix('%')->placeholder('—'),
                                TextEntry::make('captured_at')->label('Capturado')->dateTime('d/m/Y h:i A')->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function amountsSection(string $title, string $suffix, Heroicon $icon, string $color): Section
    {
        $rows = [
            'exempt' => 'Exento',
            'general_base' => 'Base general',
            'general_tax' => 'IVA general',
            'reduced_base' => 'Base reducida',
            'reduced_tax' => 'IVA reducido',
            'additional_base' => 'Base adicional',
            'additional_tax' => 'IVA adicional',
            'igtf_base' => 'Base IGTF',
            'igtf_tax' => 'IGTF',
        ];

        return Section::make($title.' (Bs)')
            ->icon($icon)
            ->iconColor($color)
            ->collapsible()
            ->collapsed($suffix !== 'sales')
            ->schema([
                Grid::make(['default' => 2, 'lg' => 5])
                    ->schema(array_map(
                        fn (string $prefix, string $label): TextEntry => TextEntry::make("{$prefix}_{$suffix}_ves")->label($label)->numeric(2),
                        array_keys($rows),
                        $rows,
                    )),
            ])
            ->columnSpanFull();
    }

    private static function pad(?int $value, int $length): string
    {
        return $value !== null ? str_pad((string) $value, $length, '0', STR_PAD_LEFT) : '—';
    }
}
