<?php

namespace App\Filament\Resources\Hr\PayrollConcepts\Tables;

use App\Enums\HrPayrollConceptApplication;
use App\Enums\HrPayrollConceptBehavior;
use App\Enums\HrPayrollConceptCurrency;
use App\Enums\HrPayrollConceptType;
use App\Models\HrPayrollConcept;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HrPayrollConceptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'payrollPeriods as upcoming_payroll_periods_count' => fn ($q) => $q
                    ->whereDate('payroll_periods.period_date', '>=', now()->toDateString()),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Concepto')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(40),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (HrPayrollConceptType|string|null $state): string => self::enumLabel(
                        $state,
                        HrPayrollConceptType::class,
                    ))
                    ->color(fn (HrPayrollConceptType|string|null $state): string => self::enumColor(
                        $state,
                        HrPayrollConceptType::class,
                    )),
                TextColumn::make('application')
                    ->label('Aplicación')
                    ->badge()
                    ->formatStateUsing(fn (HrPayrollConceptApplication|string|null $state): string => self::enumLabel(
                        $state,
                        HrPayrollConceptApplication::class,
                    ))
                    ->color(fn (HrPayrollConceptApplication|string|null $state): string => self::enumColor(
                        $state,
                        HrPayrollConceptApplication::class,
                    )),
                TextColumn::make('behavior')
                    ->label('Tipo de valor')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (HrPayrollConceptBehavior|string|null $state): string => self::enumLabel(
                        $state,
                        HrPayrollConceptBehavior::class,
                    ))
                    ->color(fn (HrPayrollConceptBehavior|string|null $state): string => self::enumColor(
                        $state,
                        HrPayrollConceptBehavior::class,
                    )),
                TextColumn::make('payroll_periods_count')
                    ->label('Periodos')
                    ->alignCenter()
                    ->state(function (HrPayrollConcept $record): string {
                        if (! $record->appliesOnSelectedPeriods()) {
                            return '—';
                        }

                        $count = (int) ($record->upcoming_payroll_periods_count ?? 0);

                        return $count === 0 ? 'Sin periodos' : (string) $count;
                    }),
                TextColumn::make('amount')
                    ->label('Monto')
                    ->alignEnd()
                    ->sortable()
                    ->state(fn (HrPayrollConcept $record): string => $record->formattedAmount()),
                TextColumn::make('currency')
                    ->label('Moneda')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(function (HrPayrollConceptCurrency|string|null $state, HrPayrollConcept $record): string {
                        if ($record->isPercentage()) {
                            return '—';
                        }

                        return self::enumLabel($state, HrPayrollConceptCurrency::class);
                    })
                    ->color(function (HrPayrollConceptCurrency|string|null $state, HrPayrollConcept $record): string {
                        if ($record->isPercentage()) {
                            return 'gray';
                        }

                        return self::enumColor($state, HrPayrollConceptCurrency::class);
                    }),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
            ->filters([
                Filter::make('concept_view')
                    ->label('Filtros')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 4,
                        ])->schema([
                            self::iosSegment(
                                'type',
                                'Tipo',
                                [
                                    'all' => 'Todos',
                                    ...HrPayrollConceptType::options(),
                                ],
                            ),
                            self::iosSegment(
                                'application',
                                'Aplicación',
                                [
                                    'all' => 'Todos',
                                    ...HrPayrollConceptApplication::options(),
                                ],
                            ),
                            self::iosSegment(
                                'behavior',
                                'Tipo de valor',
                                [
                                    'all' => 'Todos',
                                    HrPayrollConceptBehavior::Fixed->value => 'Fijo',
                                    HrPayrollConceptBehavior::Percentage->value => '%',
                                    'none' => 'Libre',
                                ],
                            ),
                            self::iosSegment(
                                'is_active',
                                'Activo',
                                [
                                    'all' => 'Todos',
                                    '1' => 'Sí',
                                    '0' => 'No',
                                ],
                            ),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $type = $data['type'] ?? 'all';
                        if (filled($type) && $type !== 'all') {
                            $query->where('type', $type);
                        }

                        $application = $data['application'] ?? 'all';
                        if (filled($application) && $application !== 'all') {
                            $query->where('application', $application);
                        }

                        $behavior = $data['behavior'] ?? 'all';
                        if ($behavior === 'none') {
                            $query->whereNull('behavior');
                        } elseif (filled($behavior) && $behavior !== 'all') {
                            $query->where('behavior', $behavior);
                        }

                        $active = $data['is_active'] ?? 'all';
                        if ($active === '1') {
                            $query->where('is_active', true);
                        } elseif ($active === '0') {
                            $query->where('is_active', false);
                        }

                        return $query;
                    }),
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

    /**
     * @param  class-string  $enumClass
     */
    private static function enumLabel(mixed $state, string $enumClass): string
    {
        if ($state instanceof $enumClass) {
            return $state->label();
        }

        if (! is_string($state) || $state === '') {
            return '—';
        }

        return $enumClass::tryFrom($state)?->label() ?? '—';
    }

    /**
     * @param  class-string  $enumClass
     */
    private static function enumColor(mixed $state, string $enumClass): string
    {
        $enum = $state instanceof $enumClass
            ? $state
            : (is_string($state) ? $enumClass::tryFrom($state) : null);

        if ($enum !== null && method_exists($enum, 'filamentColor')) {
            return $enum->filamentColor();
        }

        return 'gray';
    }

    /**
     * @param  array<string, string>  $options
     */
    private static function iosSegment(string $name, string $label, array $options): ToggleButtons
    {
        return ToggleButtons::make($name)
            ->label($label)
            ->options($options)
            ->grouped()
            ->default('all')
            ->extraAttributes([
                'class' => 'fi-hr-ios-segment',
                'data-segment-count' => (string) count($options),
            ]);
    }
}
