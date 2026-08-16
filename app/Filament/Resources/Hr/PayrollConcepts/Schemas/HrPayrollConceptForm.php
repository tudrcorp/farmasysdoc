<?php

namespace App\Filament\Resources\Hr\PayrollConcepts\Schemas;

use App\Enums\HrPayrollConceptApplication;
use App\Enums\HrPayrollConceptBehavior;
use App\Enums\HrPayrollConceptCurrency;
use App\Enums\HrPayrollConceptType;
use App\Models\HrPayrollConcept;
use App\Models\PayrollPeriod;
use App\Services\Hr\HrBcvRateResolver;
use App\Services\Hr\HrUsdVesConverter;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class HrPayrollConceptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Concepto')
                    ->icon(Heroicon::RectangleStack)
                    ->description('Asignaciones y deducciones de ley o del negocio. El valor puede ser un monto fijo o un porcentaje.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Concepto')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Grid::make(2)->schema([
                            Radio::make('type')
                                ->label('Tipo')
                                ->options(HrPayrollConceptType::options())
                                ->inline()
                                ->required()
                                ->default(HrPayrollConceptType::Assignment->value),
                            Radio::make('application')
                                ->label('Aplicación')
                                ->options(HrPayrollConceptApplication::options())
                                ->inline()
                                ->required()
                                ->default(HrPayrollConceptApplication::Business->value)
                                ->live()
                                ->afterStateUpdated(function (mixed $state, Set $set): void {
                                    if (self::isLegalApplication($state)) {
                                        $set('currency', HrPayrollConceptCurrency::Ves->value);
                                    }
                                }),
                        ]),
                        Radio::make('behavior')
                            ->label('Tipo de valor')
                            ->options(HrPayrollConceptBehavior::options())
                            ->inline()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                if (! self::isPercentageBehavior($state)) {
                                    return;
                                }

                                $amount = $get('amount');
                                if (is_numeric($amount) && (float) $amount > 100) {
                                    $set('amount', 100);
                                }
                            })
                            ->helperText('Fijo: monto en dinero. Porcentaje: se calcula sobre el sueldo correspondiente.'),
                        Select::make('payroll_period_ids')
                            ->label('Periodos de aplicación')
                            ->helperText('Solo periodos futuros. No se listan periodos vencidos, sin importar el estatus.')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->options(fn (): array => PayrollPeriod::upcomingGroupedOptions())
                            ->visible(fn (Get $get): bool => self::needsPeriodSelection(
                                $get('application'),
                                $get('behavior'),
                            ))
                            ->required(fn (Get $get): bool => self::needsPeriodSelection(
                                $get('application'),
                                $get('behavior'),
                            ))
                            ->dehydrated(fn (Get $get): bool => self::needsPeriodSelection(
                                $get('application'),
                                $get('behavior'),
                            ))
                            ->afterStateHydrated(function (Select $component, mixed $state, ?HrPayrollConcept $record): void {
                                if (! $record instanceof HrPayrollConcept || ! $record->exists) {
                                    return;
                                }

                                $component->state($record->futurePayrollPeriodIds());
                            }),
                        Radio::make('currency')
                            ->label('Moneda del monto')
                            ->options(HrPayrollConceptCurrency::options())
                            ->inline()
                            ->required(fn (Get $get): bool => ! self::isPercentageBehavior($get('behavior')))
                            ->default(HrPayrollConceptCurrency::Ves->value)
                            ->live()
                            ->visible(fn (Get $get): bool => ! self::isLegalApplication($get('application'))
                                && ! self::isPercentageBehavior($get('behavior')))
                            ->dehydrated(true),
                        TextInput::make('amount')
                            ->label(fn (Get $get): string => self::isPercentageBehavior($get('behavior'))
                                ? 'Porcentaje'
                                : 'Monto')
                            ->numeric()
                            ->minValue(fn (Get $get): float => self::amountMinValue(
                                $get('application'),
                                $get('behavior'),
                            ))
                            ->maxValue(fn (Get $get): ?int => self::isPercentageBehavior($get('behavior')) ? 100 : null)
                            ->step(0.01)
                            ->required()
                            ->prefix(fn (Get $get): ?string => self::isPercentageBehavior($get('behavior'))
                                ? null
                                : self::amountPrefix($get('application'), $get('currency')))
                            ->suffix(fn (Get $get): ?string => self::isPercentageBehavior($get('behavior')) ? '%' : null)
                            ->live(onBlur: true)
                            ->helperText(fn (Get $get): HtmlString|string => self::amountHint(
                                $get('application'),
                                $get('currency'),
                                $get('amount'),
                                $get('behavior'),
                            )),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    public static function needsPeriodSelection(mixed $application, mixed $behavior): bool
    {
        if ($behavior instanceof HrPayrollConceptBehavior) {
            if ($behavior === HrPayrollConceptBehavior::Fixed) {
                return false;
            }
        } elseif (HrPayrollConceptBehavior::tryFrom((string) $behavior) === HrPayrollConceptBehavior::Fixed) {
            return false;
        }

        if ($application instanceof HrPayrollConceptApplication) {
            return $application === HrPayrollConceptApplication::Business;
        }

        return HrPayrollConceptApplication::tryFrom((string) $application) === HrPayrollConceptApplication::Business;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function persistSelectedPeriods(HrPayrollConcept $record, array $data): void
    {
        $ids = $data['payroll_period_ids'] ?? [];

        $record->syncApplicablePayrollPeriods(
            is_array($ids) ? array_values($ids) : [],
        );
    }

    private static function isPercentageBehavior(mixed $behavior): bool
    {
        if ($behavior instanceof HrPayrollConceptBehavior) {
            return $behavior === HrPayrollConceptBehavior::Percentage;
        }

        return HrPayrollConceptBehavior::tryFrom((string) $behavior) === HrPayrollConceptBehavior::Percentage;
    }

    private static function amountMinValue(mixed $application, mixed $behavior): float
    {
        if (self::isLegalApplication($application) || self::isPercentageBehavior($behavior)) {
            return 0;
        }

        return 0.01;
    }

    private static function isLegalApplication(mixed $application): bool
    {
        if ($application instanceof HrPayrollConceptApplication) {
            return $application === HrPayrollConceptApplication::Legal;
        }

        return HrPayrollConceptApplication::tryFrom((string) $application) === HrPayrollConceptApplication::Legal;
    }

    private static function resolvedCurrency(mixed $application, mixed $currency): HrPayrollConceptCurrency
    {
        if (self::isLegalApplication($application)) {
            return HrPayrollConceptCurrency::Ves;
        }

        if ($currency instanceof HrPayrollConceptCurrency) {
            return $currency;
        }

        return HrPayrollConceptCurrency::tryFrom((string) $currency) ?? HrPayrollConceptCurrency::Ves;
    }

    private static function amountPrefix(mixed $application, mixed $currency): string
    {
        return self::resolvedCurrency($application, $currency)->prefix();
    }

    private static function amountHint(mixed $application, mixed $currency, mixed $amount, mixed $behavior): HtmlString|string
    {
        if (self::isPercentageBehavior($behavior)) {
            if (self::isLegalApplication($application)) {
                return 'Porcentaje sobre el sueldo de ley (VES). Puede ser 0.';
            }

            return 'Porcentaje sobre el sueldo quincenal en USD (mitad del sueldo mensual).';
        }

        $resolved = self::resolvedCurrency($application, $currency);

        if (self::isLegalApplication($application)) {
            return 'Los conceptos de ley se registran en bolívares. Puede ser 0.';
        }

        if ($resolved === HrPayrollConceptCurrency::Ves) {
            return 'Monto del negocio en bolívares.';
        }

        if (! is_numeric($amount) || (float) $amount <= 0) {
            return 'Monto del negocio en dólares. Equivalente en VES según tasa BCV del día.';
        }

        $rate = app(HrBcvRateResolver::class)->resolveForDate(now());
        if ($rate === null) {
            return 'No se pudo obtener la tasa BCV actual.';
        }

        $ves = number_format(HrUsdVesConverter::toVes((float) $amount, $rate), 2, ',', '.');
        $rateFmt = number_format($rate, 6, ',', '.');

        return new HtmlString("≈ <strong>Bs {$ves}</strong> (tasa BCV {$rateFmt})");
    }
}
