<?php

namespace App\Filament\Resources\Hr\PayrollPeriods\Tables;

use App\Enums\PayrollPeriodStatus;
use App\Filament\Resources\Hr\PayrollPeriods\PayrollPeriodResource;
use App\Models\PayrollPeriod;
use App\Services\Hr\HrBcvRateResolver;
use App\Services\Hr\PayrollCalculator;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class PayrollPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('period_date', 'asc')
            ->striped()
            ->paginated([12, 24, 48])
            ->defaultPaginationPageOption(24)
            ->persistFiltersInSession()
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(2)
            ->searchPlaceholder('Buscar por nº de periodo o fecha…')
            ->emptyStateHeading('No hay periodos de nómina')
            ->emptyStateDescription('Genera los 24 periodos del año con el botón «Generar periodos del año».')
            ->emptyStateIcon(Heroicon::CalendarDays)
            ->recordUrl(fn (PayrollPeriod $record): string => PayrollPeriodResource::getUrl('detail', ['record' => $record]))
            ->columns([
                TextColumn::make('period_number')
                    ->label('Periodo')
                    ->sortable(query: fn ($query, string $direction) => $query
                        ->orderBy('year', $direction)
                        ->orderBy('period_number', $direction)
                        ->orderBy('period_date', $direction))
                    ->searchable(query: function ($query, string $search): void {
                        $query->where(function ($q) use ($search): void {
                            $q->where('period_number', 'like', "%{$search}%")
                                ->orWhereDate('period_date', 'like', "%{$search}%");
                        });
                    })
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($state): string => '#'.(int) $state)
                    ->description(fn (PayrollPeriod $record): string => $record->halfLabel().' · '.$record->monthLabel())
                    ->tooltip(fn (PayrollPeriod $record): string => $record->label())
                    ->icon(Heroicon::CalendarDays)
                    ->iconColor('primary'),

                TextColumn::make('period_date')
                    ->label('Fecha de pago')
                    ->date('d/m/Y')
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray')
                    ->description(fn (PayrollPeriod $record): string => $record->isMonthEnd()
                        ? 'Cierre de mes'
                        : 'Mitad de mes'),

                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (PayrollPeriodStatus|string|null $state): string => $state instanceof PayrollPeriodStatus
                        ? $state->label()
                        : (PayrollPeriodStatus::tryFrom((string) $state)?->label() ?? '—'))
                    ->color(fn (PayrollPeriodStatus|string|null $state): string => $state instanceof PayrollPeriodStatus
                        ? $state->filamentColor()
                        : (PayrollPeriodStatus::tryFrom((string) $state)?->filamentColor() ?? 'gray'))
                    ->icon(fn (PayrollPeriodStatus|string|null $state): Heroicon => match (
                        $state instanceof PayrollPeriodStatus
                            ? $state
                            : PayrollPeriodStatus::tryFrom((string) $state)
                    ) {
                        PayrollPeriodStatus::Draft => Heroicon::Document,
                        PayrollPeriodStatus::Calculated => Heroicon::Calculator,
                        PayrollPeriodStatus::Closed => Heroicon::LockClosed,
                        default => Heroicon::QuestionMarkCircle,
                    }),

                TextColumn::make('total_assignments_usd')
                    ->label('Asignaciones')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => self::usd((float) $state))
                    ->description(fn (PayrollPeriod $record): string => self::ves((float) $record->total_assignments_ves))
                    ->color(fn (PayrollPeriod $record): string => (float) $record->total_assignments_usd > 0 ? 'success' : 'gray')
                    ->toggleable(),

                TextColumn::make('total_deductions_usd')
                    ->label('Descuentos')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => self::usd((float) $state))
                    ->description(fn (PayrollPeriod $record): string => self::ves((float) $record->total_deductions_ves))
                    ->color(fn (PayrollPeriod $record): string => (float) $record->total_deductions_usd > 0 ? 'warning' : 'gray')
                    ->toggleable(),

                TextColumn::make('total_loans_usd')
                    ->label('Préstamos')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => self::usd((float) $state))
                    ->description(fn (PayrollPeriod $record): string => self::ves((float) $record->total_loans_ves))
                    ->color(fn (PayrollPeriod $record): string => (float) $record->total_loans_usd > 0 ? 'danger' : 'gray')
                    ->toggleable(),

                TextColumn::make('total_payable_usd')
                    ->label('Total a pagar')
                    ->alignEnd()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(fn ($state): string => self::usd((float) $state))
                    ->description(fn (PayrollPeriod $record): string => self::ves((float) $record->total_payable_ves))
                    ->color('info')
                    ->icon(Heroicon::Banknotes)
                    ->iconColor('info'),

                TextColumn::make('bcv_ves_per_usd')
                    ->label('Tasa BCV')
                    ->alignEnd()
                    ->numeric(decimalPlaces: 4)
                    ->placeholder('Sin tasa')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->description(fn (PayrollPeriod $record): ?string => $record->calculated_at
                        ? 'Calc. '.$record->calculated_at->format('d/m/Y H:i')
                        : null),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label('Año')
                    ->options(fn (): array => PayrollPeriod::query()
                        ->select('year')
                        ->distinct()
                        ->orderBy('year')
                        ->pluck('year', 'year')
                        ->all())
                    ->default((string) now()->year)
                    ->selectablePlaceholder(false),
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options(PayrollPeriodStatus::options())
                    ->multiple(),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Detalle')
                    ->icon(Heroicon::Eye)
                    ->color('gray')
                    ->url(fn (PayrollPeriod $record): string => PayrollPeriodResource::getUrl('detail', ['record' => $record])),
                ActionGroup::make([
                    Action::make('calculate')
                        ->label('Calcular nómina')
                        ->icon(Heroicon::Calculator)
                        ->color('primary')
                        ->visible(fn (PayrollPeriod $record): bool => $record->status !== PayrollPeriodStatus::Closed)
                        ->form([
                            TextInput::make('manual_rate')
                                ->label('Tasa BCV manual (opcional)')
                                ->numeric()
                                ->minValue(0.000001)
                                ->step(0.000001)
                                ->helperText(function (PayrollPeriod $record): string {
                                    $rate = app(HrBcvRateResolver::class)->resolveForDate($record->period_date);

                                    return $rate !== null
                                        ? 'Tasa sugerida: '.number_format($rate, 6, ',', '.')
                                        : 'No hay tasa automática; indique una tasa manual.';
                                }),
                        ])
                        ->modalHeading(fn (PayrollPeriod $record): string => 'Calcular '.$record->label())
                        ->modalDescription('Se recalcularán todos los empleados activos con asignaciones, deducciones y préstamos aplicables.')
                        ->action(function (PayrollPeriod $record, array $data): void {
                            try {
                                $manual = isset($data['manual_rate']) && is_numeric($data['manual_rate'])
                                    ? (float) $data['manual_rate']
                                    : null;
                                app(PayrollCalculator::class)->calculate($record, $manual);
                                Notification::make()->title('Nómina calculada')->success()->send();
                            } catch (Throwable $e) {
                                Notification::make()
                                    ->title('No se pudo calcular la nómina')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),
                    Action::make('close')
                        ->label('Cerrar periodo')
                        ->icon(Heroicon::LockClosed)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Cerrar periodo de nómina')
                        ->modalDescription('Al cerrar el periodo no podrá recalcularse. Confirme solo si ya validó los totales.')
                        ->visible(fn (PayrollPeriod $record): bool => $record->status === PayrollPeriodStatus::Calculated)
                        ->action(function (PayrollPeriod $record): void {
                            try {
                                app(PayrollCalculator::class)->close($record);
                                Notification::make()->title('Periodo cerrado')->success()->send();
                            } catch (Throwable $e) {
                                Notification::make()->title('No se pudo cerrar')->body($e->getMessage())->danger()->send();
                            }
                        }),
                ])
                    ->label('Acciones')
                    ->icon(Heroicon::EllipsisVertical)
                    ->button()
                    ->color('gray'),
            ]);
    }

    private static function usd(float $amount): string
    {
        return 'US$ '.number_format($amount, 2, ',', '.');
    }

    private static function ves(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }
}
