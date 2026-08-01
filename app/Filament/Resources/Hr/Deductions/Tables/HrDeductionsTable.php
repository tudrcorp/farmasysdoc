<?php

namespace App\Filament\Resources\Hr\Deductions\Tables;

use App\Enums\HrPayCurrencyBucket;
use App\Enums\HrRecurrence;
use App\Models\HrDeduction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HrDeductionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('employee'))
            ->columns([
                TextColumn::make('employee_name')
                    ->label('Empleado')
                    ->state(fn (HrDeduction $record): string => $record->employee?->fullName() ?? '—')
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHas('employee', function ($q) use ($search): void {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('national_id', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('concept')
                    ->label('Concepto')
                    ->searchable()
                    ->wrap()
                    ->limit(40),
                TextColumn::make('amount_usd')
                    ->label('Monto USD')
                    ->money('USD')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('pay_currency_bucket')
                    ->label('Bolsillo')
                    ->badge()
                    ->formatStateUsing(fn (HrPayCurrencyBucket|string|null $state): string => $state instanceof HrPayCurrencyBucket
                        ? $state->label()
                        : (HrPayCurrencyBucket::tryFrom((string) $state)?->label() ?? '—'))
                    ->color(fn (HrPayCurrencyBucket|string|null $state): string => match (
                        $state instanceof HrPayCurrencyBucket ? $state : HrPayCurrencyBucket::tryFrom((string) $state)
                    ) {
                        HrPayCurrencyBucket::Usd => 'success',
                        HrPayCurrencyBucket::Ves => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('recurrence')
                    ->label('Recurrencia')
                    ->badge()
                    ->formatStateUsing(fn (HrRecurrence|string|null $state): string => $state instanceof HrRecurrence
                        ? $state->label()
                        : (HrRecurrence::tryFrom((string) $state)?->label() ?? '—')),
                TextColumn::make('applies_on')
                    ->label('Aplica el')
                    ->date('d/m/Y')
                    ->placeholder('Recurrente'),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('recurrence')
                    ->label('Recurrencia')
                    ->options(HrRecurrence::options()),
                TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
