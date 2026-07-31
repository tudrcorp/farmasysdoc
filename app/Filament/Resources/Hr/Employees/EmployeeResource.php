<?php

namespace App\Filament\Resources\Hr\Employees;

use App\Filament\Resources\Hr\Concerns\ChecksHrAccess;
use App\Filament\Resources\Hr\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Hr\Employees\Pages\EditEmployee;
use App\Filament\Resources\Hr\Employees\Pages\ListEmployees;
use App\Filament\Resources\Hr\Employees\Pages\ViewEmployee;
use App\Filament\Resources\Hr\Employees\Schemas\EmployeeForm;
use App\Filament\Resources\Hr\Employees\Schemas\EmployeeInfolist;
use App\Filament\Resources\Hr\Employees\Tables\EmployeesTable;
use App\Models\Employee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EmployeeResource extends Resource
{
    use ChecksHrAccess;

    protected static ?string $model = Employee::class;

    protected static ?string $navigationLabel = 'Empleados';

    protected static ?string $modelLabel = 'empleado';

    protected static ?string $pluralModelLabel = 'empleados';

    protected static ?string $recordTitleAttribute = 'national_id';

    protected static string|UnitEnum|null $navigationGroup = 'hr';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if ($record instanceof Employee) {
            return $record->fullName();
        }

        return parent::getRecordTitle($record);
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'view' => ViewEmployee::route('/{record}'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
