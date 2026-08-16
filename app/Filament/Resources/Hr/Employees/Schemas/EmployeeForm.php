<?php

namespace App\Filament\Resources\Hr\Employees\Schemas;

use App\Enums\EmployeeBankAccountType;
use App\Enums\HrPayCurrencyBucket;
use App\Enums\VenezuelanPagoMovilBank;
use App\Services\Hr\HrBcvRateResolver;
use App\Services\Hr\HrUsdVesConverter;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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

                Section::make('Firma y huella')
                    ->icon(Heroicon::PencilSquare)
                    ->description('Imágenes del expediente para recibos y documentos de nómina. También se pueden pedir al empleado desde el portal.')
                    ->schema([
                        FileUpload::make('signature_path')
                            ->label('Firma del empleado')
                            ->helperText('Opcional. Suba una foto o escaneo de la firma manuscrita (PNG, JPG o WEBP).')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                null,
                                '3:1',
                                '4:1',
                            ])
                            ->panelLayout('integrated')
                            ->disk('public')
                            ->directory('employees/signatures')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                        FileUpload::make('fingerprint_path')
                            ->label('Huella dactilar')
                            ->helperText('Opcional. Foto o escaneo de la huella (PNG, JPG o WEBP). El empleado también puede cargarla desde el portal.')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->imageEditor()
                            ->panelLayout('integrated')
                            ->disk('public')
                            ->directory('employees/fingerprints')
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
                    ->description('El sueldo en USD se usa para el pago quincenal. El sueldo de ley se carga en bolívares y es independiente.')
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
                            TextInput::make('legal_salary_ves')
                                ->label('Sueldo de ley (VES)')
                                ->numeric()
                                ->minValue(0.01)
                                ->step(0.01)
                                ->required()
                                ->prefix('Bs')
                                ->live(onBlur: true)
                                ->helperText(fn (Get $get): string => self::legalSalaryHint($get('legal_salary_ves'))),
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
                            Fieldset::make('1.ª quincena (día 15)')
                                ->schema([
                                    Radio::make('first_half_pay_currency')
                                        ->label('Moneda de pago')
                                        ->options(HrPayCurrencyBucket::paymentOptions())
                                        ->inline()
                                        ->required()
                                        ->default(HrPayCurrencyBucket::Ves->value)
                                        ->live()
                                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                                            if (self::isVesCurrency($state)) {
                                                $set('first_half_usd_cash', 0);
                                            }
                                        })
                                        ->helperText(function (Get $get): HtmlString|string {
                                            if (self::isUsdCurrency($get('first_half_pay_currency'))) {
                                                return '';
                                            }

                                            return self::fortnightPayHint(
                                                $get('monthly_salary_usd'),
                                                $get('first_half_pay_currency'),
                                                0,
                                            );
                                        }),
                                    TextInput::make('first_half_usd_cash')
                                        ->label('Monto en dólares')
                                        ->numeric()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->default(0)
                                        ->prefix('US$')
                                        ->live(onBlur: true)
                                        ->visible(fn (Get $get): bool => self::isUsdCurrency($get('first_half_pay_currency')))
                                        ->dehydrated()
                                        ->rule(fn (Get $get): \Closure => self::usdCashMaxRule($get))
                                        ->helperText(fn (Get $get): HtmlString|string => self::fortnightPayHint(
                                            $get('monthly_salary_usd'),
                                            $get('first_half_pay_currency'),
                                            $get('first_half_usd_cash'),
                                        )),
                                ]),
                            Fieldset::make('2.ª quincena (fin de mes)')
                                ->schema([
                                    Radio::make('second_half_pay_currency')
                                        ->label('Moneda de pago')
                                        ->options(HrPayCurrencyBucket::paymentOptions())
                                        ->inline()
                                        ->required()
                                        ->default(HrPayCurrencyBucket::Ves->value)
                                        ->live()
                                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                                            if (self::isVesCurrency($state)) {
                                                $set('second_half_usd_cash', 0);
                                            }
                                        })
                                        ->helperText(function (Get $get): HtmlString|string {
                                            if (self::isUsdCurrency($get('second_half_pay_currency'))) {
                                                return '';
                                            }

                                            return self::fortnightPayHint(
                                                $get('monthly_salary_usd'),
                                                $get('second_half_pay_currency'),
                                                0,
                                            );
                                        }),
                                    TextInput::make('second_half_usd_cash')
                                        ->label('Monto en dólares')
                                        ->numeric()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->default(0)
                                        ->prefix('US$')
                                        ->live(onBlur: true)
                                        ->visible(fn (Get $get): bool => self::isUsdCurrency($get('second_half_pay_currency')))
                                        ->dehydrated()
                                        ->rule(fn (Get $get): \Closure => self::usdCashMaxRule($get))
                                        ->helperText(fn (Get $get): HtmlString|string => self::fortnightPayHint(
                                            $get('monthly_salary_usd'),
                                            $get('second_half_pay_currency'),
                                            $get('second_half_usd_cash'),
                                        )),
                                ]),
                        ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function biweeklyBaseDescription(mixed $monthlySalary): string
    {
        if (! is_numeric($monthlySalary) || (float) $monthlySalary <= 0) {
            return 'Indique el sueldo mensual. Luego elija la moneda y, si paga en dólares, el monto en USD; el resto de la base se paga en bolívares.';
        }

        $base = number_format(round((float) $monthlySalary / 2, 2), 2, ',', '.');

        return "Base quincenal: US$ {$base}. Cargue solo el monto en dólares; si no indica ninguno, toda la quincena se paga en bolívares.";
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

    private static function fortnightPayHint(mixed $monthlySalary, mixed $currency, mixed $usdCash): HtmlString|string
    {
        if (! is_numeric($monthlySalary) || (float) $monthlySalary <= 0) {
            return 'Indique el sueldo mensual para ver el cálculo de esta quincena.';
        }

        $base = round((float) $monthlySalary / 2, 2);
        $cash = self::isUsdCurrency($currency) && is_numeric($usdCash)
            ? min(max(0, (float) $usdCash), $base)
            : 0.0;
        $remainderUsd = round(max(0, $base - $cash), 2);

        if ($cash <= 0) {
            return self::vesEquivalentHint(
                $base,
                'Sin monto en dólares: toda la quincena se paga en bolívares',
            );
        }

        if ($remainderUsd <= 0) {
            return new HtmlString('Toda la base se paga en dólares: <strong>US$ '.number_format($cash, 2, ',', '.').'</strong>.');
        }

        return self::vesEquivalentHint(
            $remainderUsd,
            'Diferencia en dólares (se paga en bolívares)',
        );
    }

    private static function vesEquivalentHint(float $usd, string $prefix): HtmlString
    {
        $usdLabel = number_format($usd, 2, ',', '.');
        $rate = app(HrBcvRateResolver::class)->resolveForDate(now());

        if ($rate === null) {
            return new HtmlString("{$prefix}: <strong>US$ {$usdLabel}</strong> (tasa BCV no disponible).");
        }

        $ves = number_format(HrUsdVesConverter::toVes($usd, $rate), 2, ',', '.');

        return new HtmlString("{$prefix}: <strong>US$ {$usdLabel}</strong> ≈ <strong>Bs {$ves}</strong>");
    }

    private static function isUsdCurrency(mixed $currency): bool
    {
        if ($currency instanceof HrPayCurrencyBucket) {
            return $currency === HrPayCurrencyBucket::Usd;
        }

        return HrPayCurrencyBucket::tryFrom((string) $currency) === HrPayCurrencyBucket::Usd;
    }

    private static function isVesCurrency(mixed $currency): bool
    {
        return ! self::isUsdCurrency($currency);
    }

    private static function legalSalaryHint(mixed $ves): string
    {
        if (! is_numeric($ves) || (float) $ves <= 0) {
            return 'Monto mensual en bolívares. No se calcula desde el sueldo en USD ni con la tasa BCV.';
        }

        $monthly = number_format((float) $ves, 2, ',', '.');
        $biweekly = number_format(round((float) $ves / 2, 2), 2, ',', '.');

        return "Sueldo de ley: Bs {$monthly} · quincenal: Bs {$biweekly}.";
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
