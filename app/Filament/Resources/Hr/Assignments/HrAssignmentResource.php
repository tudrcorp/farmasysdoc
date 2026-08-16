<?php

namespace App\Filament\Resources\Hr\Assignments;

use App\Filament\Resources\Hr\Assignments\Pages\CreateHrAssignment;
use App\Filament\Resources\Hr\Assignments\Pages\EditHrAssignment;
use App\Filament\Resources\Hr\Assignments\Pages\ListHrAssignments;
use App\Filament\Resources\Hr\Assignments\Schemas\HrAssignmentForm;
use App\Filament\Resources\Hr\Assignments\Tables\HrAssignmentsTable;
use App\Filament\Resources\Hr\Concerns\ChecksHrAccess;
use App\Models\HrAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HrAssignmentResource extends Resource
{
    use ChecksHrAccess;

    protected static ?string $model = HrAssignment::class;

    protected static ?string $navigationLabel = 'Asignaciones';

    protected static ?string $modelLabel = 'asignación';

    protected static ?string $pluralModelLabel = 'asignaciones';

    protected static string|UnitEnum|null $navigationGroup = 'hr';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::PlusCircle;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return HrAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HrAssignmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHrAssignments::route('/'),
            'create' => CreateHrAssignment::route('/create'),
            'edit' => EditHrAssignment::route('/{record}/edit'),
        ];
    }
}
