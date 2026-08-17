<?php

namespace App\Filament\Resources\PosTerminals\Schemas;

use App\Enums\VenezuelanPagoMovilBank;
use App\Models\Branch;
use App\Support\Filament\BranchAuthScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Unique;

class PosTerminalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Punto de venta')
                    ->description('Registra el código del terminal y el banco al que pertenece, asociado a una sucursal.')
                    ->icon(Heroicon::CreditCard)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])
                            ->schema([
                                Select::make('branch_id')
                                    ->label('Sucursal')
                                    ->options(fn (): array => Branch::query()
                                        ->where('is_active', true)
                                        ->tap(fn ($query) => BranchAuthScope::applyToBranchFormSelect($query))
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->native(false)
                                    ->prefixIcon(Heroicon::BuildingStorefront)
                                    ->default(fn (): ?int => BranchAuthScope::suggestedBranchIdForOperationalForm())
                                    ->helperText('Sucursal donde opera este punto de venta.'),
                                Select::make('bank_code')
                                    ->label('Banco')
                                    ->options(VenezuelanPagoMovilBank::optionsForSelect())
                                    ->searchable()
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->prefixIcon(Heroicon::BuildingLibrary)
                                    ->helperText('Se guarda el código de 4 dígitos de la institución.'),
                                TextInput::make('code')
                                    ->label('Código del punto')
                                    ->required()
                                    ->maxLength(32)
                                    ->autocomplete('off')
                                    ->prefixIcon(Heroicon::Hashtag)
                                    ->placeholder('Ej. 00123456')
                                    ->helperText('Código del terminal asignado por el banco. Debe ser único por banco.')
                                    ->dehydrateStateUsing(fn (?string $state): string => trim((string) $state))
                                    ->unique(
                                        ignoreRecord: true,
                                        modifyRuleUsing: function (Unique $rule, Get $get): Unique {
                                            return $rule->where('bank_code', (string) $get('bank_code'));
                                        },
                                    )
                                    ->validationMessages([
                                        'unique' => 'Ya existe un punto de venta con este código para el banco seleccionado.',
                                    ]),
                                Toggle::make('is_active')
                                    ->label('Punto activo')
                                    ->helperText('Desactívelo para dejar de usarlo sin borrar el registro.')
                                    ->default(true)
                                    ->inline(false),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
