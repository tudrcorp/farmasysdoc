<?php

namespace App\Filament\Resources\PurchaseHistories\Schemas;

use App\Filament\Resources\AccountsPayables\AccountsPayableResource;
use App\Models\PurchaseHistory;
use App\Support\Purchases\PurchaseHistoryEntryType;
use App\Support\Purchases\PurchaseHistoryPaymentForm;
use App\Support\Purchases\PurchaseHistoryPaymentMethod;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PurchaseHistoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Clasificación del movimiento')
                    ->description('Identifica si es una compra registrada al contado o un pago aplicado a una cuenta por pagar.')
                    ->icon(Heroicon::Tag)
                    ->schema([
                        TextEntry::make('entry_type')
                            ->label('Tipo de registro')
                            ->formatStateUsing(fn (?string $state): string => PurchaseHistoryEntryType::label($state))
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                PurchaseHistoryEntryType::COMPRA_CONTADO => 'success',
                                PurchaseHistoryEntryType::PAGO_CUENTA_POR_PAGAR => 'info',
                                default => 'gray',
                            }),
                        TextEntry::make('created_at')
                            ->label('Fecha y hora de registro en histórico')
                            ->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('created_by')
                            ->label('Usuario o actor del registro')
                            ->placeholder('—'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Documento de compra (contexto)')
                    ->description('Datos de la factura y del proveedor tal como quedaron asentados en el sistema en este movimiento.')
                    ->icon(Heroicon::DocumentText)
                    ->schema([
                        TextEntry::make('purchase.purchase_number')
                            ->label('Nº orden de compra interna')
                            ->placeholder('—'),
                        TextEntry::make('branch.name')
                            ->label('Sucursal')
                            ->placeholder('—'),
                        TextEntry::make('supplier_name')
                            ->label('Nombre del proveedor'),
                        TextEntry::make('supplier_tax_id')
                            ->label('RIF')
                            ->placeholder('—'),
                        TextEntry::make('supplier_invoice_number')
                            ->label('Nº de factura del proveedor'),
                        TextEntry::make('supplier_control_number')
                            ->label('Nº de control fiscal')
                            ->placeholder('—'),
                        TextEntry::make('issued_at')
                            ->label('Fecha de emisión de la factura')
                            ->date('d/m/Y'),
                        TextEntry::make('registered_in_system_date')
                            ->label('Fecha de registro del documento en el sistema')
                            ->date('d/m/Y'),
                        TextEntry::make('accounts_payable_id')
                            ->label('Cuenta por pagar relacionada')
                            ->formatStateUsing(fn (?int $state): string => $state ? 'CxP #'.$state : 'No aplica (compra al contado)')
                            ->url(fn (PurchaseHistory $record): ?string => $record->accounts_payable_id
                                ? AccountsPayableResource::getUrl('view', ['record' => $record->accounts_payable_id], isAbsolute: false)
                                : null),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Montos del documento (referencia)')
                    ->description('Totales de la compra en USD y en bolívares según tasas BCV oficiales (promedio) en fechas clave. En abonos a crédito reflejan el documento original; el pago en sí se detalla en la siguiente sección.')
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        TextEntry::make('purchase_total_usd')
                            ->label('Total de la compra (USD)')
                            ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' USD'),
                        TextEntry::make('purchase_total_ves_at_issue')
                            ->label('Total en Bs según tasa del día de emisión de la factura')
                            ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                        TextEntry::make('total_ves_at_system_registration')
                            ->label('Total en Bs según tasa del día de registro del documento en sistema')
                            ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Detalle del pago al proveedor')
                    ->description('Solo aplica cuando el movimiento es un pago contra una cuenta por pagar (crédito).')
                    ->icon(Heroicon::CurrencyDollar)
                    ->visible(fn (mixed $record): bool => $record instanceof PurchaseHistory
                        && $record->entry_type === PurchaseHistoryEntryType::PAGO_CUENTA_POR_PAGAR)
                    ->schema([
                        TextEntry::make('payment_method')
                            ->label('Método de pago')
                            ->formatStateUsing(fn (?string $state): string => PurchaseHistoryPaymentMethod::label($state)),
                        TextEntry::make('payment_form')
                            ->label('Forma de pago')
                            ->formatStateUsing(fn (?string $state): string => PurchaseHistoryPaymentForm::label($state)),
                        TextEntry::make('paid_at')
                            ->label('Fecha y hora en que se realizó el pago')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('amount_paid_ves')
                            ->label('Monto pagado en bolívares')
                            ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                        TextEntry::make('amount_paid_usd')
                            ->label('Monto del pago en USD')
                            ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' USD'),
                        TextEntry::make('bcv_rate_at_payment')
                            ->label('Tasa BCV (Bs/USD) aplicada al pago')
                            ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2, ',', '.').' Bs/USD' : '—'),
                        TextEntry::make('payment_reference')
                            ->label('Referencia del pago')
                            ->placeholder('—'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Notas')
                    ->icon(Heroicon::ChatBubbleBottomCenterText)
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Observaciones')
                            ->placeholder('Sin notas')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (mixed $record): bool => $record instanceof PurchaseHistory && filled($record->notes))
                    ->columnSpanFull(),
            ]);
    }

    private static function formatBs(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }
}
