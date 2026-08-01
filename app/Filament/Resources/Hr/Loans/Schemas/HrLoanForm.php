<?php

namespace App\Filament\Resources\Hr\Loans\Schemas;

use App\Enums\HrLoanFrequency;
use App\Enums\HrLoanInstallmentMode;
use App\Enums\HrPayCurrencyBucket;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hr\HrBcvRateResolver;
use App\Services\Hr\HrUsdVesConverter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class HrLoanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Préstamo')
                    ->icon(Heroicon::Banknotes)
                    ->description('Acuerde con el empleado la frecuencia y la modalidad de descuento del sueldo.')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Empleado')
                            ->options(fn (): array => self::employeeOptions())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $employee = Employee::query()->find($state);
                                $set('branch_id', $employee?->branch_id);
                            }),
                        Select::make('branch_id')
                            ->label('Sucursal')
                            ->relationship('branch', 'name')
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                        Textarea::make('concept')
                            ->label('Concepto / descripción')
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make('amount_usd')
                            ->label('Monto del préstamo (USD)')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required()
                            ->prefix('US$')
                            ->live(onBlur: true)
                            ->helperText(fn (Get $get): HtmlString|string => self::vesHint($get('amount_usd'))),
                        Select::make('pay_currency_bucket')
                            ->label('Bolsillo de descuento')
                            ->options(HrPayCurrencyBucket::options())
                            ->required()
                            ->native(false)
                            ->default(HrPayCurrencyBucket::Ves->value)
                            ->helperText('Sobre qué porción del pago quincenal se descuentan las cuotas (monto siempre en USD).'),
                        Select::make('frequency')
                            ->label('Frecuencia de descuento')
                            ->options(HrLoanFrequency::options())
                            ->required()
                            ->native(false)
                            ->helperText('Quincenal: se descuenta el 15 y el cierre de mes. Mensual: solo el cierre de mes.'),
                        Select::make('installment_mode')
                            ->label('Modalidad de cuota')
                            ->options(HrLoanInstallmentMode::options())
                            ->required()
                            ->native(false)
                            ->live(),
                        Grid::make(2)->schema([
                            TextInput::make('fixed_installment_usd')
                                ->label('Cuota fija (USD)')
                                ->numeric()
                                ->minValue(0.01)
                                ->step(0.01)
                                ->prefix('US$')
                                ->required(fn (Get $get): bool => $get('installment_mode') === HrLoanInstallmentMode::Fixed->value)
                                ->visible(fn (Get $get): bool => $get('installment_mode') === HrLoanInstallmentMode::Fixed->value),
                            TextInput::make('installments_count')
                                ->label('Número de cuotas')
                                ->numeric()
                                ->minValue(1)
                                ->integer()
                                ->visible(fn (Get $get): bool => $get('installment_mode') === HrLoanInstallmentMode::Fixed->value),
                            TextInput::make('salary_percentage')
                                ->label('% del sueldo')
                                ->numeric()
                                ->minValue(0.01)
                                ->maxValue(100)
                                ->step(0.01)
                                ->suffix('%')
                                ->required(fn (Get $get): bool => $get('installment_mode') === HrLoanInstallmentMode::Percentage->value)
                                ->visible(fn (Get $get): bool => $get('installment_mode') === HrLoanInstallmentMode::Percentage->value)
                                ->helperText('Sobre el sueldo quincenal (si es quincenal) o mensual (si es mensual), hasta saldar el saldo.'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int|string, string>
     */
    private static function employeeOptions(): array
    {
        $query = Employee::query()->where('is_active', true)->orderBy('last_name')->orderBy('first_name');

        $user = auth()->user();
        if ($user instanceof User && ! $user->isAdministrator()) {
            $branchIds = $user->restrictedBranchIdsForQueries();
            if ($branchIds === []) {
                return [];
            }
            $query->whereIn('branch_id', $branchIds);
        }

        return $query->get()
            ->mapWithKeys(fn (Employee $e): array => [$e->id => "{$e->fullName()} ({$e->national_id})"])
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

        return new HtmlString("≈ <strong>Bs {$ves}</strong>");
    }
}
