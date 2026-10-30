<?php

namespace App\Filament\Resources\Hr\Severance\Settlements\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

final class SettlementInputsSchema
{
    /**
     * @return list<Grid>
     */
    public static function fields(): array
    {
        $usd = fn (string $name, string $label, ?string $help = null): TextInput => TextInput::make($name)
            ->label($label)
            ->helperText($help)
            ->numeric()
            ->minValue(0)
            ->step(0.01)
            ->default(0)
            ->prefix('US$')
            ->required();

        $days = fn (string $name, string $label): TextInput => TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->maxValue(365)
            ->step(0.5)
            ->default(0)
            ->suffix('días')
            ->required();

        return [
            Grid::make(2)->schema([
                $usd('guarantee_accrued_usd', 'Garantía acumulada', 'Depósitos trimestrales de prestaciones.'),
                $usd('accrued_interest_usd', 'Intereses acumulados'),
                $usd('advances_usd', 'Anticipos de prestaciones'),
                $usd('utilities_paid_usd', 'Utilidades adelantadas este año'),
                $days('pending_vacation_days', 'Vacaciones vencidas'),
                $days('pending_bonus_days', 'Bono vacacional vencido'),
                $days('unpaid_salary_days', 'Días de salario sin pagar'),
            ]),
        ];
    }
}
