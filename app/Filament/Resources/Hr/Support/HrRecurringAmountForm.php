<?php

namespace App\Filament\Resources\Hr\Support;

use App\Enums\HrPayCurrencyBucket;
use App\Enums\HrRecurrence;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Services\Hr\HrBcvRateResolver;
use App\Services\Hr\HrUsdVesConverter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

final class HrRecurringAmountForm
{
    /**
     * @return array<int, mixed>
     */
    public static function components(string $amountLabel, bool $withPayCurrencyBucket = false): array
    {
        return [
            Section::make('Detalle')
                ->icon(Heroicon::DocumentText)
                ->schema([
                    Select::make('employee_id')
                        ->label('Empleado')
                        ->options(fn (): array => Employee::query()
                            ->where('is_active', true)
                            ->orderBy('last_name')
                            ->orderBy('first_name')
                            ->get()
                            ->mapWithKeys(fn (Employee $e): array => [$e->id => "{$e->fullName()} ({$e->national_id})"])
                            ->all())
                        ->searchable()
                        ->required(),
                    TextInput::make('concept')
                        ->label('Concepto / descripción')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Grid::make(2)->schema([
                        TextInput::make('amount_usd')
                            ->label($amountLabel)
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required()
                            ->prefix('US$')
                            ->live(onBlur: true)
                            ->helperText(fn (Get $get): HtmlString|string => self::vesHint($get('amount_usd'))),
                        Select::make('recurrence')
                            ->label('Recurrencia')
                            ->options(HrRecurrence::options())
                            ->required()
                            ->native(false)
                            ->live(),
                    ]),
                    Select::make('pay_currency_bucket')
                        ->label('Bolsillo de descuento')
                        ->options(HrPayCurrencyBucket::options())
                        ->native(false)
                        ->default(HrPayCurrencyBucket::Ves->value)
                        ->helperText('Sobre qué porción del pago quincenal se aplica este descuento.')
                        ->visible($withPayCurrencyBucket)
                        ->dehydrated($withPayCurrencyBucket)
                        ->required($withPayCurrencyBucket),
                    DatePicker::make('applies_on')
                        ->label('Periodo de aplicación')
                        ->helperText('Solo fechas de nómina (día 15 o fin de mes).')
                        ->native(false)
                        ->required(fn (Get $get): bool => $get('recurrence') === HrRecurrence::Once->value)
                        ->visible(fn (Get $get): bool => $get('recurrence') === HrRecurrence::Once->value)
                        ->rule(function (): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail): void {
                                if (! is_string($value) || $value === '') {
                                    return;
                                }
                                if (! self::isPayrollPeriodDate($value)) {
                                    $fail('La fecha debe ser un día 15 o el último día del mes.');
                                }
                            };
                        }),
                    Grid::make(2)->schema([
                        DatePicker::make('starts_on')
                            ->label('Vigente desde')
                            ->native(false),
                        DatePicker::make('ends_on')
                            ->label('Vigente hasta')
                            ->native(false),
                    ])->visible(fn (Get $get): bool => $get('recurrence') === HrRecurrence::Recurring->value),
                    Toggle::make('is_active')
                        ->label('Activo')
                        ->default(true),
                ])
                ->columns(1)
                ->columnSpanFull(),
        ];
    }

    public static function isPayrollPeriodDate(string $date): bool
    {
        try {
            $carbon = Carbon::parse($date)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        if ($carbon->day === 15) {
            return true;
        }

        return $carbon->day === $carbon->copy()->endOfMonth()->day;
    }

    /**
     * @return array<string, string>
     */
    public static function periodDateOptions(?int $year = null): array
    {
        $year ??= (int) now()->year;

        return PayrollPeriod::query()
            ->where('year', $year)
            ->orderBy('period_number')
            ->get()
            ->mapWithKeys(fn (PayrollPeriod $p): array => [
                $p->period_date->toDateString() => $p->label(),
            ])
            ->all();
    }

    private static function vesHint(mixed $usd): HtmlString|string
    {
        if (! is_numeric($usd) || (float) $usd <= 0) {
            return 'Equivalente en VES según tasa BCV del día.';
        }

        $rate = app(HrBcvRateResolver::class)->resolveForDate(now());
        if ($rate === null) {
            return 'No se pudo obtener la tasa BCV actual.';
        }

        $ves = number_format(HrUsdVesConverter::toVes((float) $usd, $rate), 2, ',', '.');
        $rateFmt = number_format($rate, 6, ',', '.');

        return new HtmlString("≈ <strong>Bs {$ves}</strong> (tasa BCV {$rateFmt})");
    }
}
