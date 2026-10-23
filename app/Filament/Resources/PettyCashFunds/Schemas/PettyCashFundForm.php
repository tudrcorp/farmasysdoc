<?php

namespace App\Filament\Resources\PettyCashFunds\Schemas;

use App\Enums\EmployeeBankAccountType;
use App\Enums\PettyCashCurrency;
use App\Enums\VenezuelanPagoMovilBank;
use App\Services\Hr\HrBcvRateResolver;
use App\Support\Filament\BranchAuthScope;
use App\Support\Finance\PettyCashMoney;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class PettyCashFundForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Solicitud')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->description('Indique sucursal, fecha, monto y el motivo de la caja chica.')
                    ->schema([
                        Select::make('branch_id')
                            ->label('Sucursal')
                            ->relationship(
                                name: 'branch',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query): Builder {
                                    $query->where('is_active', true)->orderBy('name');

                                    return BranchAuthScope::applyToBranchFormSelect($query);
                                },
                            )
                            ->default(fn (): ?int => BranchAuthScope::suggestedBranchIdForOperationalForm())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->prefixIcon(Heroicon::BuildingStorefront),
                        DatePicker::make('requested_at')
                            ->label('Fecha de solicitud')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->prefixIcon(Heroicon::CalendarDays),
                        Select::make('requested_currency')
                            ->label('Moneda del monto')
                            ->options(PettyCashCurrency::options())
                            ->default(PettyCashCurrency::Usd->value)
                            ->required()
                            ->native(false)
                            ->live(),
                        TextInput::make('requested_amount')
                            ->label('Monto requerido')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required()
                            ->live(onBlur: true)
                            ->prefix(fn (Get $get): string => PettyCashCurrency::tryFrom((string) $get('requested_currency'))?->prefix() ?? 'US$')
                            ->helperText(fn (Get $get): HtmlString|string => self::conversionHint(
                                $get('requested_currency'),
                                $get('requested_amount'),
                            )),
                        Textarea::make('notes')
                            ->label('Nota o descripción')
                            ->helperText('Indique por qué requiere la caja chica.')
                            ->rows(3)
                            ->required()
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Cuenta bancaria de destino')
                    ->icon(Heroicon::BuildingLibrary)
                    ->description('Datos para Pago Móvil o transferencia. Titular, tipo y número de cuenta son opcionales.')
                    ->schema([
                        Select::make('bank_code')
                            ->label('Banco')
                            ->options(VenezuelanPagoMovilBank::optionsForSelect())
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->placeholder('Seleccione el banco'),
                        Select::make('bank_account_type')
                            ->label('Tipo de cuenta')
                            ->options(EmployeeBankAccountType::options())
                            ->native(false)
                            ->nullable()
                            ->placeholder('Corriente o ahorro'),
                        TextInput::make('bank_account_holder')
                            ->label('Titular de la cuenta')
                            ->maxLength(255)
                            ->nullable()
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null),
                        TextInput::make('holder_national_id')
                            ->label('Cédula')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('V-12345678')
                            ->helperText('Cédula del titular. Ejemplo: V-12345678.'),
                        TextInput::make('holder_phone')
                            ->label('Número de teléfono')
                            ->tel()
                            ->required()
                            ->maxLength(40)
                            ->placeholder('0412-1234567')
                            ->helperText('Teléfono del titular, para transferencia o Pago Móvil.'),
                        TextInput::make('bank_account_number')
                            ->label('Número de cuenta')
                            ->maxLength(30)
                            ->nullable()
                            ->rules(['nullable', 'regex:/^[0-9\s-]+$/'])
                            ->validationMessages([
                                'regex' => 'El número de cuenta solo puede contener dígitos, espacios o guiones.',
                            ])
                            ->dehydrateStateUsing(function (?string $state): ?string {
                                if (! filled($state)) {
                                    return null;
                                }

                                $digits = preg_replace('/\D+/', '', $state) ?? '';

                                return filled($digits) ? $digits : null;
                            })
                            ->helperText('Opcional. Solo dígitos. Se normalizan al guardar.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    private static function conversionHint(mixed $currency, mixed $amount): HtmlString|string
    {
        if (! is_numeric($amount) || (float) $amount <= 0) {
            return 'El equivalente se calcula con la tasa BCV del día.';
        }

        $enum = $currency instanceof PettyCashCurrency
            ? $currency
            : PettyCashCurrency::tryFrom((string) $currency);
        if (! $enum instanceof PettyCashCurrency) {
            return 'El equivalente se calcula con la tasa BCV del día.';
        }

        $rate = app(HrBcvRateResolver::class)->resolveForDate(now());
        if ($rate === null || $rate <= 0) {
            return 'No se pudo obtener la tasa BCV actual.';
        }

        try {
            $split = PettyCashMoney::split($enum, (float) $amount, $rate);
        } catch (\InvalidArgumentException) {
            return 'No se pudo convertir el monto.';
        }

        if ($enum === PettyCashCurrency::Usd) {
            return new HtmlString('≈ <strong>'.e(PettyCashMoney::formatVes($split['amount_ves'])).'</strong>');
        }

        return new HtmlString('≈ <strong>'.e(PettyCashMoney::formatUsd($split['amount_usd'])).'</strong>');
    }
}
