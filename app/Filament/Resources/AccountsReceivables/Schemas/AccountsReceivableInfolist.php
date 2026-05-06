<?php

namespace App\Filament\Resources\AccountsReceivables\Schemas;

use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\AccountsReceivable;
use App\Support\Finance\AccountsReceivableStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AccountsReceivableInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cliente y venta')
                    ->icon(Heroicon::User)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->formatStateUsing(fn (?string $state): string => AccountsReceivableStatus::label($state))
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        AccountsReceivableStatus::COBRADO => 'success',
                                        AccountsReceivableStatus::CANCELADO => 'gray',
                                        default => 'warning',
                                    }),
                                TextEntry::make('sale_number_snapshot')
                                    ->label('Nº de venta')
                                    ->icon(Heroicon::ShoppingBag)
                                    ->url(fn (AccountsReceivable $record): ?string => $record->sale_id
                                        ? SaleResource::getUrl('view', ['record' => $record->sale_id], isAbsolute: false)
                                        : null),
                                TextEntry::make('branch.name')
                                    ->label('Sucursal')
                                    ->placeholder('—')
                                    ->icon(Heroicon::BuildingStorefront)
                                    ->url(fn (AccountsReceivable $record): ?string => $record->branch_id
                                        ? BranchResource::getUrl('view', ['record' => $record->branch_id], isAbsolute: false)
                                        : null),
                                TextEntry::make('client_name_snapshot')
                                    ->label('Cliente')
                                    ->icon(Heroicon::UserCircle),
                                TextEntry::make('client_document_snapshot')
                                    ->label('Documento')
                                    ->placeholder('—'),
                                TextEntry::make('issued_at')
                                    ->label('Emisión')
                                    ->date('d/m/Y'),
                                TextEntry::make('due_at')
                                    ->label('Vencimiento')
                                    ->date('d/m/Y')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Montos')
                    ->description('Referencias en USD y bolívares según datos de la venta y la tasa BCV registrada.')
                    ->icon(Heroicon::CurrencyDollar)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('sale_total_usd')
                                    ->label('Total de la venta (USD)')
                                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' USD'),
                                TextEntry::make('paid_equivalent_usd')
                                    ->label('Cobrado equivalente (USD)')
                                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' USD'),
                                TextEntry::make('remaining_principal_usd')
                                    ->label('Saldo pendiente principal (USD)')
                                    ->weight('medium')
                                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' USD'),
                                TextEntry::make('payment_usd_snapshot')
                                    ->label('Pago USD (instantáneo)')
                                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' USD'),
                                TextEntry::make('payment_ves_snapshot')
                                    ->label('Pago VES (instantáneo)')
                                    ->formatStateUsing(fn ($state): string => 'Bs. '.number_format((float) $state, 2, ',', '.')),
                                TextEntry::make('bcv_ves_per_usd_snapshot')
                                    ->label('Tasa BCV (instantáneo)')
                                    ->placeholder('—')
                                    ->formatStateUsing(fn (?float $state): string => $state !== null && $state > 0
                                        ? 'Bs. '.number_format((float) $state, 6, ',', '.').' / USD'
                                        : '—'),
                                TextEntry::make('sale_total_ves_reference')
                                    ->label('Total venta en Bs. (referencia)')
                                    ->formatStateUsing(fn ($state): string => 'Bs. '.number_format((float) $state, 2, ',', '.')),
                                TextEntry::make('original_balance_ves')
                                    ->label('Saldo original en Bs.')
                                    ->formatStateUsing(fn ($state): string => 'Bs. '.number_format((float) $state, 2, ',', '.')),
                                TextEntry::make('current_balance_ves')
                                    ->label('Saldo actual en Bs.')
                                    ->formatStateUsing(fn ($state): string => 'Bs. '.number_format((float) $state, 2, ',', '.')),
                                TextEntry::make('last_balance_recalculated_at')
                                    ->label('Último recálculo')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('notes')
                                    ->label('Notas')
                                    ->placeholder('Sin notas')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
