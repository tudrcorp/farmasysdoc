<?php

namespace App\Filament\Resources\Hr\PayrollPeriods;

use App\Filament\Resources\Hr\Concerns\ChecksHrAccess;
use App\Filament\Resources\Hr\PayrollPeriods\Pages\ListPayrollPeriods;
use App\Filament\Resources\Hr\PayrollPeriods\Pages\ViewPayrollPeriodDetail;
use App\Filament\Resources\Hr\PayrollPeriods\Tables\PayrollPeriodsTable;
use App\Models\PayrollPeriod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PayrollPeriodResource extends Resource
{
    use ChecksHrAccess;

    protected static ?string $model = PayrollPeriod::class;

    protected static ?string $navigationLabel = 'Nómina';

    protected static ?string $modelLabel = 'periodo de nómina';

    protected static ?string $pluralModelLabel = 'periodos de nómina';

    protected static string|UnitEnum|null $navigationGroup = 'hr';

    protected static ?int $navigationSort = 50;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Calculator;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return PayrollPeriodsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollPeriods::route('/'),
            'detail' => ViewPayrollPeriodDetail::route('/{record}/detalle'),
        ];
    }
}
