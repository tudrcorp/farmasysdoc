<?php

namespace App\Filament\Resources\Hr\Deductions;

use App\Filament\Resources\Hr\Concerns\ChecksHrAccess;
use App\Filament\Resources\Hr\Deductions\Pages\CreateHrDeduction;
use App\Filament\Resources\Hr\Deductions\Pages\EditHrDeduction;
use App\Filament\Resources\Hr\Deductions\Pages\ListHrDeductions;
use App\Filament\Resources\Hr\Deductions\Schemas\HrDeductionForm;
use App\Filament\Resources\Hr\Deductions\Tables\HrDeductionsTable;
use App\Models\HrDeduction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HrDeductionResource extends Resource
{
    use ChecksHrAccess;

    protected static ?string $model = HrDeduction::class;

    protected static ?string $navigationLabel = 'Deducciones';

    protected static ?string $modelLabel = 'deducción';

    protected static ?string $pluralModelLabel = 'deducciones';

    protected static string|UnitEnum|null $navigationGroup = 'hr';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::MinusCircle;

    public static function form(Schema $schema): Schema
    {
        return HrDeductionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HrDeductionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHrDeductions::route('/'),
            'create' => CreateHrDeduction::route('/create'),
            'edit' => EditHrDeduction::route('/{record}/edit'),
        ];
    }
}
