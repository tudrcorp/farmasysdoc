<?php

namespace App\Filament\Resources\AccountsPayables\Schemas;

use App\Support\Finance\AccountsPayableStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AccountsPayableInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Documento y proveedor')
                    ->icon(Heroicon::DocumentText)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->formatStateUsing(fn (?string $state): string => AccountsPayableStatus::label($state))
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        AccountsPayableStatus::PAGADO => 'success',
                                        default => 'warning',
                                    }),
                                TextEntry::make('purchase.purchase_number')
                                    ->label('Nº orden de compra')
                                    ->placeholder('—')
                                    ->icon(Heroicon::ShoppingCart),
                                TextEntry::make('branch.name')
                                    ->label('Sucursal')
                                    ->placeholder('—')
                                    ->icon(Heroicon::BuildingStorefront),
                                TextEntry::make('supplier_name')
                                    ->label('Nombre del proveedor')
                                    ->icon(Heroicon::Truck),
                                TextEntry::make('supplier_tax_id')
                                    ->label('RIF')
                                    ->placeholder('—'),
                                TextEntry::make('supplier_invoice_number')
                                    ->label('Nº de factura')
                                    ->icon(Heroicon::Hashtag),
                                TextEntry::make('supplier_control_number')
                                    ->label('Nº de control')
                                    ->placeholder('—'),
                                TextEntry::make('issued_at')
                                    ->label('Fecha de emisión')
                                    ->date('d/m/Y'),
                                TextEntry::make('due_at')
                                    ->label('Fecha de vencimiento')
                                    ->date('d/m/Y')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Montos')
                    ->description('Totales y saldos en bolívares según tasa BCV oficial (promedio). El saldo al día se actualiza con la tarea programada (07:00, hora Caracas).')
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('remaining_principal_usd')
                                    ->label('Principal pendiente (USD)')
                                    ->formatStateUsing(fn ($state): string => number_format((float) ($state ?? 0), 2, ',', '.').' USD')
                                    ->placeholder('—'),
                                TextEntry::make('purchase_total_usd')
                                    ->label('Total de la compra (USD)')
                                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' USD'),
                                TextEntry::make('purchase_total_ves_at_issue')
                                    ->label('Total en Bs (tasa del día de emisión de la factura)')
                                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                                TextEntry::make('original_balance_ves')
                                    ->label('Saldo original en Bs (día de registro en sistema)')
                                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                                TextEntry::make('current_balance_ves')
                                    ->label('Saldo al día actual en Bs')
                                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                                TextEntry::make('last_balance_recalculated_at')
                                    ->label('Último recálculo automático del saldo')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function formatBs(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }
}
