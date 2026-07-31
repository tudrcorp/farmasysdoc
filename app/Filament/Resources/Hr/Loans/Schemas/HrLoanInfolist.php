<?php

namespace App\Filament\Resources\Hr\Loans\Schemas;

use App\Enums\HrLoanFrequency;
use App\Enums\HrLoanInstallmentMode;
use App\Enums\HrLoanStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HrLoanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Préstamo')
                    ->schema([
                        TextEntry::make('employee.first_name')
                            ->label('Empleado')
                            ->formatStateUsing(fn ($state, $record): string => $record->employee?->fullName() ?? '—'),
                        TextEntry::make('branch.name')->label('Sucursal'),
                        TextEntry::make('concept')->label('Concepto')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('amount_usd')->label('Monto')->money('USD'),
                        TextEntry::make('remaining_usd')->label('Saldo')->money('USD'),
                        TextEntry::make('frequency')
                            ->label('Frecuencia')
                            ->formatStateUsing(fn (HrLoanFrequency|string|null $state): string => $state instanceof HrLoanFrequency
                                ? $state->label()
                                : (HrLoanFrequency::tryFrom((string) $state)?->label() ?? '—')),
                        TextEntry::make('installment_mode')
                            ->label('Modalidad')
                            ->formatStateUsing(fn (HrLoanInstallmentMode|string|null $state): string => $state instanceof HrLoanInstallmentMode
                                ? $state->label()
                                : (HrLoanInstallmentMode::tryFrom((string) $state)?->label() ?? '—')),
                        TextEntry::make('fixed_installment_usd')->label('Cuota fija')->money('USD')->placeholder('—'),
                        TextEntry::make('salary_percentage')->label('% sueldo')->suffix('%')->placeholder('—'),
                        TextEntry::make('status')
                            ->label('Estatus')
                            ->badge()
                            ->formatStateUsing(fn (HrLoanStatus|string|null $state): string => $state instanceof HrLoanStatus
                                ? $state->label()
                                : (HrLoanStatus::tryFrom((string) $state)?->label() ?? '—'))
                            ->color(fn (HrLoanStatus|string|null $state): string => $state instanceof HrLoanStatus
                                ? $state->filamentColor()
                                : (HrLoanStatus::tryFrom((string) $state)?->filamentColor() ?? 'gray')),
                        TextEntry::make('requestedBy.name')->label('Solicitado por')->placeholder('—'),
                        TextEntry::make('approvedBy.name')->label('Aprobado/rechazado por')->placeholder('—'),
                        TextEntry::make('approved_at')->label('Fecha decisión')->dateTime('d/m/Y H:i')->placeholder('—'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
