<?php

namespace App\Filament\Resources\Hr\Employees\Schemas;

use App\Enums\EmployeeBankAccountType;
use App\Enums\VenezuelanPagoMovilBank;
use App\Services\Hr\HrBcvRateResolver;
use App\Services\Hr\HrUsdVesConverter;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos personales')
                    ->icon(Heroicon::User)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('first_name')
                                ->label('Nombre')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('last_name')
                                ->label('Apellido')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('national_id')
                                ->label('Cédula de identidad')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(50),
                            TextInput::make('phone')
                                ->label('Teléfono')
                                ->tel()
                                ->maxLength(40),
                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->maxLength(255),
                            Select::make('branch_id')
                                ->label('Sucursal')
                                ->relationship(
                                    'branch',
                                    'name',
                                    fn ($query) => $query->where('is_active', true)->orderBy('name'),
                                )
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
                        Textarea::make('address')
                            ->label('Dirección')
                            ->rows(2)
                            ->columnSpanFull(),
                        FileUpload::make('photo_path')
                            ->label('Foto del empleado')
                            ->helperText('Opcional. Si no se carga, en el listado se muestran las iniciales.')
                            ->image()
                            ->avatar()
                            ->imageEditor()
                            ->circleCropper()
                            ->disk('public')
                            ->directory('employees')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Datos bancarios')
                    ->icon(Heroicon::BuildingLibrary)
                    ->description('Cuenta para depositar el pago en bolívares u otras transferencias de nómina.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('bank_code')
                                ->label('Banco')
                                ->options(VenezuelanPagoMovilBank::optionsForSelect())
                                ->searchable()
                                ->native(false)
                                ->placeholder('Seleccione el banco')
                                ->helperText('Se guarda con su código de 4 dígitos.'),
                            Select::make('bank_account_type')
                                ->label('Tipo de cuenta')
                                ->options(EmployeeBankAccountType::options())
                                ->native(false)
                                ->placeholder('Corriente o ahorro'),
                            TextInput::make('bank_account_number')
                                ->label('Número de cuenta')
                                ->maxLength(30)
                                ->rule('regex:/^[0-9\s-]+$/')
                                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                    ? preg_replace('/\D+/', '', $state)
                                    : null)
                                ->helperText('Solo dígitos (hasta 20). Se normalizan al guardar.')
                                ->columnSpanFull(),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Sueldo y salario')
                    ->icon(Heroicon::Banknotes)
                    ->description('El sueldo mensual se divide en dos pagos quincenales (15 y cierre de mes).')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('monthly_salary_usd')
                                ->label('Sueldo mensual (USD)')
                                ->numeric()
                                ->minValue(0.01)
                                ->step(0.01)
                                ->required()
                                ->prefix('US$')
                                ->live(onBlur: true)
                                ->helperText(fn (Get $get): HtmlString|string => self::vesHint($get('monthly_salary_usd'))),
                            Toggle::make('is_active')
                                ->label('Empleado activo')
                                ->default(true)
                                ->inline(false),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Forma de pago por quincena')
                    ->icon(Heroicon::CurrencyDollar)
                    ->description(fn (Get $get): string => self::biweeklyBaseDescription($get('monthly_salary_usd')))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('first_half_usd_cash')
                                ->label('USD efectivo — 1.ª quincena (día 15)')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01)
                                ->required()
                                ->default(0)
                                ->prefix('US$')
                                ->live(onBlur: true)
                                ->rule(fn (Get $get): \Closure => self::usdCashMaxRule($get))
                                ->helperText(fn (Get $get): HtmlString|string => self::remainderHint(
                                    $get('monthly_salary_usd'),
                                    $get('first_half_usd_cash'),
                                )),
                            TextInput::make('second_half_usd_cash')
                                ->label('USD efectivo — 2.ª quincena (fin de mes)')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01)
                                ->required()
                                ->default(0)
                                ->prefix('US$')
                                ->live(onBlur: true)
                                ->rule(fn (Get $get): \Closure => self::usdCashMaxRule($get))
                                ->helperText(fn (Get $get): HtmlString|string => self::remainderHint(
                                    $get('monthly_salary_usd'),
                                    $get('second_half_usd_cash'),
                                )),
                        ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function biweeklyBaseDescription(mixed $monthlySalary): string
    {
        if (! is_numeric($monthlySalary) || (float) $monthlySalary <= 0) {
            return 'Indique el sueldo mensual. La base de cada quincena es sueldo ÷ 2; el resto de esa base (tras el USD efectivo) se paga en bolívares a tasa BCV.';
        }

        $base = number_format(round((float) $monthlySalary / 2, 2), 2, ',', '.');

        return "Base quincenal: US$ {$base}. Configure cuánto de esa base se paga en efectivo USD; el resto va en Bs a tasa BCV.";
    }

    private static function usdCashMaxRule(Get $get): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
            if (! is_numeric($value)) {
                return;
            }

            $monthly = $get('monthly_salary_usd');
            if (! is_numeric($monthly) || (float) $monthly <= 0) {
                return;
            }

            $max = round((float) $monthly / 2, 2);
            if ((float) $value > $max) {
                $fail('No puede superar la base quincenal (US$ '.number_format($max, 2, ',', '.').').');
            }
        };
    }

    private static function remainderHint(mixed $monthlySalary, mixed $usdCash): HtmlString|string
    {
        if (! is_numeric($monthlySalary) || (float) $monthlySalary <= 0) {
            return 'Resto de la base quincenal en bolívares (tasa BCV).';
        }

        $base = round((float) $monthlySalary / 2, 2);
        $cash = is_numeric($usdCash) ? max(0, (float) $usdCash) : 0.0;
        $remainderUsd = round(max(0, $base - $cash), 2);

        $rate = app(HrBcvRateResolver::class)->resolveForDate(now());
        if ($rate === null) {
            return 'Resto en Bs: US$ '.number_format($remainderUsd, 2, ',', '.').' (tasa BCV no disponible).';
        }

        $ves = number_format(HrUsdVesConverter::toVes($remainderUsd, $rate), 2, ',', '.');

        return new HtmlString(
            'Resto en Bs: <strong>US$ '.number_format($remainderUsd, 2, ',', '.').'</strong> ≈ <strong>Bs '.$ves.'</strong>',
        );
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
