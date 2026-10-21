<?php

namespace App\Filament\Resources\PettyCashFunds\Schemas;

use App\Enums\EmployeeBankAccountType;
use App\Enums\PettyCashCurrency;
use App\Enums\PettyCashFundStatus;
use App\Models\PettyCashExpense;
use App\Models\PettyCashFund;
use App\Support\Finance\PettyCashMoney;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class PettyCashFundInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Solicitud')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->description('Datos cargados por gerencia.')
                    ->schema([
                        TextEntry::make('branch.name')->label('Sucursal'),
                        TextEntry::make('requested_at')->label('Fecha de solicitud')->date('d/m/Y'),
                        TextEntry::make('requestedBy.name')->label('Solicitado por'),
                        TextEntry::make('status')
                            ->label('Estatus')
                            ->badge()
                            ->formatStateUsing(fn (PettyCashFundStatus|string|null $state): string => $state instanceof PettyCashFundStatus
                                ? $state->label()
                                : (PettyCashFundStatus::tryFrom((string) $state)?->label() ?? '—'))
                            ->color(fn (PettyCashFundStatus|string|null $state): string => $state instanceof PettyCashFundStatus
                                ? $state->filamentColor()
                                : (PettyCashFundStatus::tryFrom((string) $state)?->filamentColor() ?? 'gray')),
                        TextEntry::make('requested_amount')
                            ->label(fn (PettyCashFund $record): string => $record->isReplenishment()
                                ? 'Monto a transferir (pago)'
                                : 'Monto solicitado')
                            ->state(fn (PettyCashFund $record): string => ($record->requested_currency instanceof PettyCashCurrency
                                ? $record->requested_currency->prefix().' '
                                : '')
                                .number_format((float) $record->requested_amount, 2, ',', '.')),
                        TextEntry::make('carried_over_ves')
                            ->label('Saldo anterior (solo sistema)')
                            ->state(fn (PettyCashFund $record): string => PettyCashMoney::formatUsd((float) $record->carried_over_usd)
                                .' / '
                                .PettyCashMoney::formatVes((float) $record->carried_over_ves))
                            ->visible(fn (PettyCashFund $record): bool => $record->isReplenishment() && (float) $record->carried_over_usd > 0.009),
                        TextEntry::make('amount_usd')
                            ->label(fn (PettyCashFund $record): string => $record->isReplenishment()
                                ? 'Total del fondo USD'
                                : 'Equivalente USD')
                            ->state(fn (PettyCashFund $record): string => PettyCashMoney::formatUsd((float) $record->amount_usd)),
                        TextEntry::make('amount_ves')
                            ->label(fn (PettyCashFund $record): string => $record->isReplenishment()
                                ? 'Total del fondo VES'
                                : 'Equivalente VES')
                            ->state(fn (PettyCashFund $record): string => PettyCashMoney::formatVes((float) $record->amount_ves)),
                        TextEntry::make('replenished_from_fund_id')
                            ->label('Reposición de caja')
                            ->formatStateUsing(fn (?int $state): string => $state !== null ? '#'.$state : '—')
                            ->visible(fn (PettyCashFund $record): bool => $record->replenished_from_fund_id !== null),
                        TextEntry::make('remaining_usd')
                            ->label('Disponible USD')
                            ->weight(FontWeight::Bold)
                            ->state(fn (PettyCashFund $record): string => PettyCashMoney::formatUsd((float) $record->remaining_usd)),
                        TextEntry::make('remaining_ves')
                            ->label('Disponible VES')
                            ->weight(FontWeight::Bold)
                            ->state(fn (PettyCashFund $record): string => PettyCashMoney::formatVes((float) $record->remaining_ves)),
                        TextEntry::make('notes')
                            ->label('Nota / motivo')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Cuenta bancaria de destino')
                    ->icon(Heroicon::BuildingLibrary)
                    ->schema([
                        TextEntry::make('bank_code')
                            ->label('Banco')
                            ->state(fn (PettyCashFund $record): string => $record->bankLabel()),
                        TextEntry::make('bank_account_type')
                            ->label('Tipo')
                            ->placeholder('—')
                            ->formatStateUsing(fn (EmployeeBankAccountType|string|null $state): string => $state instanceof EmployeeBankAccountType
                                ? $state->label()
                                : (EmployeeBankAccountType::tryFrom((string) $state)?->label() ?? '—')),
                        TextEntry::make('bank_account_holder')
                            ->label('Titular')
                            ->placeholder('—'),
                        TextEntry::make('holder_national_id')
                            ->label('Cédula')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('holder_phone')
                            ->label('Teléfono')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('bank_account_number')
                            ->label('Número de cuenta')
                            ->placeholder('—')
                            ->copyable(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Aprobación y comprobante')
                    ->icon(Heroicon::CheckBadge)
                    ->description('Pago registrado por administración.')
                    ->visible(fn (PettyCashFund $record): bool => $record->status !== PettyCashFundStatus::PendingApproval)
                    ->schema([
                        TextEntry::make('approvedBy.name')->label('Aprobado / rechazado por')->placeholder('—'),
                        TextEntry::make('approved_at')->label('Fecha de decisión')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('payment_reference')->label('Referencia')->placeholder('—')->copyable(),
                        TextEntry::make('rejection_reason')->label('Motivo de rechazo')->placeholder('—')->columnSpanFull(),
                        ImageEntry::make('payment_proof_path')
                            ->label('Comprobante')
                            ->disk('public')
                            ->visibility('public')
                            ->height(220)
                            ->visible(fn (PettyCashFund $record): bool => filled($record->payment_proof_path)
                                && ! str_ends_with(strtolower((string) $record->payment_proof_path), '.pdf'))
                            ->columnSpanFull(),
                        TextEntry::make('payment_proof_link')
                            ->label('Comprobante (PDF)')
                            ->state(fn (PettyCashFund $record): ?string => filled($record->payment_proof_path)
                                ? Storage::disk('public')->url($record->payment_proof_path)
                                : null)
                            ->url(fn (PettyCashFund $record): ?string => filled($record->payment_proof_path)
                                ? Storage::disk('public')->url($record->payment_proof_path)
                                : null)
                            ->openUrlInNewTab()
                            ->visible(fn (PettyCashFund $record): bool => filled($record->payment_proof_path)
                                && str_ends_with(strtolower((string) $record->payment_proof_path), '.pdf'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Gastos registrados')
                    ->icon(Heroicon::ReceiptPercent)
                    ->description('Cada gasto descuenta el disponible de esta caja chica.')
                    ->schema([
                        RepeatableEntry::make('expenses')
                            ->label('')
                            ->placeholder('Aún no hay gastos. Cuando la solicitud esté aprobada, registre la factura o el ticket.')
                            ->table([
                                TableColumn::make('Fecha'),
                                TableColumn::make('Descripción'),
                                TableColumn::make('Moneda'),
                                TableColumn::make('Monto'),
                                TableColumn::make('USD'),
                                TableColumn::make('VES'),
                            ])
                            ->schema([
                                TextEntry::make('spent_at')->dateTime('d/m/Y H:i'),
                                TextEntry::make('description')->limit(40),
                                TextEntry::make('currency')
                                    ->formatStateUsing(fn (PettyCashCurrency|string|null $state): string => $state instanceof PettyCashCurrency
                                        ? $state->shortLabel()
                                        : (PettyCashCurrency::tryFrom((string) $state)?->shortLabel() ?? '—')),
                                TextEntry::make('amount')
                                    ->state(fn (PettyCashExpense $record): string => number_format((float) $record->amount, 2, ',', '.')),
                                TextEntry::make('amount_usd')
                                    ->state(fn (PettyCashExpense $record): string => PettyCashMoney::formatUsd((float) $record->amount_usd)),
                                TextEntry::make('amount_ves')
                                    ->state(fn (PettyCashExpense $record): string => PettyCashMoney::formatVes((float) $record->amount_ves)),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
