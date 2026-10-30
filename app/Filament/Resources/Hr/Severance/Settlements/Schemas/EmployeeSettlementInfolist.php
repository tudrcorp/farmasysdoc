<?php

namespace App\Filament\Resources\Hr\Severance\Settlements\Schemas;

use App\Enums\EmployeeSettlementLineType;
use App\Enums\EmployeeSettlementStatus;
use App\Models\EmployeeSettlement;
use App\Models\EmployeeSettlementLine;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EmployeeSettlementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumen')
                    ->icon(Heroicon::Calculator)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 4])->schema([
                            TextEntry::make('employee_name')->label('Empleado')->state(fn (EmployeeSettlement $record): string => $record->employeeExit->employee->fullName()),
                            TextEntry::make('status')
                                ->label('Estado')
                                ->badge()
                                ->formatStateUsing(fn (EmployeeSettlementStatus $state): string => $state->label())
                                ->color(fn (EmployeeSettlementStatus $state): string => $state->filamentColor()),
                            TextEntry::make('hired_at')->label('Ingreso')->date('d/m/Y'),
                            TextEntry::make('last_work_day')->label('Último día')->date('d/m/Y'),
                            TextEntry::make('service_label')->label('Tiempo de servicio'),
                            TextEntry::make('monthly_salary_usd')->label('Sueldo mensual')->state(fn (EmployeeSettlement $record): string => self::usd($record->monthly_salary_usd)),
                            TextEntry::make('normal_daily_usd')->label('Salario normal diario')->prefix('US$ '),
                            TextEntry::make('integral_daily_usd')->label('Salario integral diario')->prefix('US$ '),
                            TextEntry::make('total_assignments_usd')->label('Asignaciones')->state(fn (EmployeeSettlement $record): string => self::usd($record->total_assignments_usd)),
                            TextEntry::make('total_deductions_usd')->label('Deducciones')->state(fn (EmployeeSettlement $record): string => self::usd($record->total_deductions_usd)),
                            TextEntry::make('net_usd')->label('Neto a pagar')->weight('bold')->state(fn (EmployeeSettlement $record): string => self::usd($record->net_usd)),
                            TextEntry::make('net_ves')
                                ->label('Neto en bolívares')
                                ->state(fn (EmployeeSettlement $record): ?string => $record->net_ves !== null ? 'Bs '.number_format((float) $record->net_ves, 2, ',', '.') : null)
                                ->helperText(fn (EmployeeSettlement $record): ?string => $record->bcv_ves_per_usd !== null ? 'Tasa BCV '.($record->bcv_ves_per_usd + 0) : null)
                                ->placeholder('Se fija al aprobar'),
                        ]),
                    ])
                    ->columnSpanFull(),
                self::linesSection('Asignaciones', EmployeeSettlementLineType::Assignment),
                self::linesSection('Deducciones', EmployeeSettlementLineType::Deduction),
                Section::make('Trazabilidad')
                    ->icon(Heroicon::ShieldCheck)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            TextEntry::make('approver.name')->label('Aprobada por')->placeholder('—'),
                            TextEntry::make('approved_at')->label('Fecha de aprobación')->dateTime('d/m/Y h:i a')->placeholder('—'),
                            TextEntry::make('payer.name')->label('Pagada por')->placeholder('—'),
                            TextEntry::make('paid_at')->label('Fecha de pago')->dateTime('d/m/Y h:i a')->placeholder('—'),
                            TextEntry::make('payment_method')->label('Método')->placeholder('—'),
                            TextEntry::make('payment_reference')->label('Referencia')->placeholder('—'),
                        ]),
                        TextEntry::make('cancel_reason')->label('Motivo de anulación')->visible(fn (EmployeeSettlement $record): bool => filled($record->cancel_reason)),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function linesSection(string $title, EmployeeSettlementLineType $type): Section
    {
        return Section::make($title)
            ->schema([
                RepeatableEntry::make('lines_'.$type->value)
                    ->hiddenLabel()
                    ->state(fn (EmployeeSettlement $record) => $record->lines->where('type', $type)->values())
                    ->table([
                        TableColumn::make('Concepto'),
                        TableColumn::make('Fórmula'),
                        TableColumn::make('Monto'),
                    ])
                    ->schema([
                        TextEntry::make('label')->weight('medium')->wrap(),
                        TextEntry::make('formula')
                            ->wrap()
                            ->state(fn (EmployeeSettlementLine $record): string => $record->isAdjusted()
                                ? "{$record->formula}. Ajustado: {$record->adjustment_reason} (calculado ".self::usd($record->calculated_usd).')'
                                : $record->formula),
                        TextEntry::make('amount_usd')->state(fn (EmployeeSettlementLine $record): string => self::usd($record->amount_usd)),
                    ]),
            ])
            ->visible(fn (EmployeeSettlement $record): bool => $record->lines->where('type', $type)->isNotEmpty())
            ->columnSpanFull();
    }

    private static function usd(mixed $amount): string
    {
        return 'US$ '.number_format((float) $amount, 2, ',', '.');
    }
}
