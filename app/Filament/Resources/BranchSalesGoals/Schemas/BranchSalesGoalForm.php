<?php

namespace App\Filament\Resources\BranchSalesGoals\Schemas;

use App\Models\Branch;
use App\Models\BranchSalesGoal;
use App\Support\Filament\BranchAuthScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class BranchSalesGoalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Periodo y alcance')
                    ->description('Define el mes calendario y si la meta aplica a toda la empresa o a una sucursal.')
                    ->icon(Heroicon::CalendarDays)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])
                            ->schema([
                                Select::make('period_year')
                                    ->label('Año')
                                    ->options(BranchSalesGoal::yearOptions())
                                    ->default((int) now()->year)
                                    ->required()
                                    ->native(false)
                                    ->live(),
                                Select::make('period_month')
                                    ->label('Mes')
                                    ->options(BranchSalesGoal::monthOptions())
                                    ->default((int) now()->month)
                                    ->required()
                                    ->native(false)
                                    ->live(),
                            ]),
                        Toggle::make('is_global')
                            ->label('Meta global')
                            ->helperText('Activa para fijar la meta consolidada de todas las sucursales.')
                            ->default(false)
                            ->live(),
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
                            ->required(fn (Get $get): bool => ! (bool) $get('is_global'))
                            ->visible(fn (Get $get): bool => ! (bool) $get('is_global'))
                            ->disabled(fn (Get $get): bool => (bool) $get('is_global'))
                            ->dehydrated(fn (Get $get): bool => ! (bool) $get('is_global'))
                            ->native(false),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Meta en dólares')
                    ->description('Monto objetivo de ventas en USD para el periodo seleccionado.')
                    ->icon(Heroicon::CurrencyDollar)
                    ->schema([
                        TextInput::make('goal_usd')
                            ->label('Meta (USD)')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required()
                            ->prefix('$')
                            ->helperText('Solo se registran metas en dólares; el cumplimiento se medirá contra cobros en USD.'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeScopeFields(array $data): array
    {
        $isGlobal = (bool) ($data['is_global'] ?? false);

        if ($isGlobal) {
            $data['branch_id'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function assertUniqueScope(array $data, ?BranchSalesGoal $record = null): void
    {
        $isGlobal = (bool) ($data['is_global'] ?? false);
        $year = (int) ($data['period_year'] ?? 0);
        $month = (int) ($data['period_month'] ?? 0);
        $branchId = $isGlobal ? null : ($data['branch_id'] ?? null);

        $exists = BranchSalesGoal::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('is_global', $isGlobal)
            ->when(
                $isGlobal,
                fn ($query) => $query->whereNull('branch_id'),
                fn ($query) => $query->where('branch_id', $branchId),
            )
            ->when(
                $record !== null,
                fn ($query) => $query->whereKeyNot($record->getKey()),
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'period_month' => 'Ya existe una meta para este periodo y alcance.',
            ]);
        }
    }
}
