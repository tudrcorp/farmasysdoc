<?php

namespace App\Filament\Resources\Hr\PayrollPeriods\Tables;

use App\Enums\PayrollPeriodStatus;
use App\Filament\Resources\Hr\PayrollPeriods\PayrollPeriodResource;
use App\Models\PayrollPeriod;
use App\Services\Hr\HrBcvRateResolver;
use App\Services\Hr\PayrollCalculator;
use App\Services\Hr\PayrollPeriodVisibility;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class PayrollPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('period_date', 'asc')
            ->striped()
            ->paginated(false)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
            ->searchPlaceholder('Buscar por nº de periodo o fecha…')
            ->emptyStateHeading('No hay un periodo visible hoy')
            ->emptyStateDescription('Solo se muestra el periodo pendiente de calcular. Para ver otro, elija periodo o estatus en los filtros.')
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

                TextColumn::make('visibility')
                    ->label('Visibilidad')
                    ->state(fn (PayrollPeriod $record): string => self::visibilityState($record)['label'])
                    ->badge()
                    ->color(fn (PayrollPeriod $record): string => self::visibilityState($record)['color'])
                    ->icon(fn (PayrollPeriod $record): Heroicon => self::visibilityState($record)['color'] === 'warning'
                        ? Heroicon::Clock
                        : Heroicon::CheckCircle)
                    ->description(fn (PayrollPeriod $record): string => self::visibilityState($record)['description']),

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
                Filter::make('payroll_view')
                    ->label('Filtros')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            Select::make('period_id')
                                ->label('Periodo')
                                ->placeholder('Vigente')
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->options(fn (): array => self::consultPeriodOptions())
                                ->extraAttributes(['class' => 'fi-hr-ios-select']),
                            ToggleButtons::make('status')
                                ->label('Estatus')
                                ->options([
                                    'vigente' => 'Vigente',
                                    ...PayrollPeriodStatus::options(),
                                ])
                                ->grouped()
                                ->default('vigente')
                                ->extraAttributes([
                                    'class' => 'fi-hr-ios-segment',
                                    'data-segment-count' => '4',
                                ]),
                        ]),
                    ])
                    ->baseQuery(function (Builder $query, array $data): Builder {
                        $statuses = self::selectedStatuses($data['status'] ?? 'vigente');

                        $periodId = filled($data['period_id'] ?? null)
                            ? (int) $data['period_id']
                            : null;

                        return app(PayrollPeriodVisibility::class)->constrainList(
                            $query,
                            $periodId,
                            $statuses,
                        );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        $periodId = $data['period_id'] ?? null;

                        if (filled($periodId)) {
                            $period = PayrollPeriod::query()->find((int) $periodId);
                            $indicators[] = $period instanceof PayrollPeriod
                                ? 'Periodo '.$period->label()
                                : 'Periodo consultado';
                        }

                        $status = $data['status'] ?? 'vigente';
                        $statuses = self::selectedStatuses($status);

                        if ($statuses !== []) {
                            $labels = array_map(
                                fn (string $value): string => PayrollPeriodStatus::tryFrom($value)?->label() ?? $value,
                                $statuses,
                            );
                            $indicators[] = 'Estatus: '.implode(', ', $labels);
                        }

                        return $indicators;
                    }),
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
                        ->modalDescription('Se recalcularán los empleados activos con el sueldo quincenal en USD, sus asignaciones y deducciones, los conceptos del negocio aplicables y los préstamos.')
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

    /**
     * @return list<string>
     */
    private static function selectedStatuses(mixed $status): array
    {
        if (is_array($status)) {
            return array_values(array_filter(
                array_map(fn (mixed $value): string => (string) $value, $status),
                fn (string $value): bool => $value !== '' && $value !== 'vigente',
            ));
        }

        if (! is_string($status) || $status === '' || $status === 'vigente') {
            return [];
        }

        return [$status];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function consultPeriodOptions(): array
    {
        $grouped = [];

        PayrollPeriod::query()
            ->orderByDesc('year')
            ->orderBy('period_number')
            ->get()
            ->each(function (PayrollPeriod $period) use (&$grouped): void {
                $status = $period->status instanceof PayrollPeriodStatus
                    ? $period->status->label()
                    : (PayrollPeriodStatus::tryFrom((string) $period->status)?->label() ?? '—');

                $grouped[(string) $period->year][$period->id] = sprintf(
                    '#%d · %s · %s · %s',
                    $period->period_number,
                    $period->period_date->format('d/m/Y'),
                    $period->halfLabel(),
                    $status,
                );
            });

        return $grouped;
    }

    /**
     * @return array{label: string, color: string, description: string}
     */
    private static function visibilityState(PayrollPeriod $record): array
    {
        return app(PayrollPeriodVisibility::class)->tableState($record);
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
