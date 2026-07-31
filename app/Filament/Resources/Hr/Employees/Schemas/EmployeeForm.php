<?php

namespace App\Filament\Resources\Hr\Employees\Schemas;

use App\Services\Hr\HrBcvRateResolver;
use App\Services\Hr\HrUsdVesConverter;
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
            ]);
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
