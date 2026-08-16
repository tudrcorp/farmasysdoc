<?php

namespace App\Filament\Resources\Hr\PayrollConcepts;

use App\Filament\Resources\Hr\Concerns\ChecksHrAccess;
use App\Filament\Resources\Hr\PayrollConcepts\Pages\CreateHrPayrollConcept;
use App\Filament\Resources\Hr\PayrollConcepts\Pages\EditHrPayrollConcept;
use App\Filament\Resources\Hr\PayrollConcepts\Pages\ListHrPayrollConcepts;
use App\Filament\Resources\Hr\PayrollConcepts\Schemas\HrPayrollConceptForm;
use App\Filament\Resources\Hr\PayrollConcepts\Tables\HrPayrollConceptsTable;
use App\Models\HrPayrollConcept;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HrPayrollConceptResource extends Resource
{
    use ChecksHrAccess;

    protected static ?string $model = HrPayrollConcept::class;

    protected static ?string $navigationLabel = 'Conceptos de Nómina';

    protected static ?string $modelLabel = 'concepto de nómina';

    protected static ?string $pluralModelLabel = 'conceptos de nómina';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'hr';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::RectangleStack;

    public static function form(Schema $schema): Schema
    {
        return HrPayrollConceptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HrPayrollConceptsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHrPayrollConcepts::route('/'),
            'create' => CreateHrPayrollConcept::route('/create'),
            'edit' => EditHrPayrollConcept::route('/{record}/edit'),
        ];
    }
}
