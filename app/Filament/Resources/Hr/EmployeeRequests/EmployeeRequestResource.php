<?php

namespace App\Filament\Resources\Hr\EmployeeRequests;

use App\Enums\EmployeeRequestStatus;
use App\Filament\Resources\Hr\Concerns\ChecksHrAccess;
use App\Filament\Resources\Hr\EmployeeRequests\Pages\ListEmployeeRequests;
use App\Filament\Resources\Hr\EmployeeRequests\Pages\ViewEmployeeRequest;
use App\Filament\Resources\Hr\EmployeeRequests\Schemas\EmployeeRequestInfolist;
use App\Filament\Resources\Hr\EmployeeRequests\Tables\EmployeeRequestsTable;
use App\Models\EmployeeRequest;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EmployeeRequestResource extends Resource
{
    use ChecksHrAccess;

    protected static ?string $model = EmployeeRequest::class;

    protected static ?string $navigationLabel = 'Solicitudes de empleados';

    protected static ?string $modelLabel = 'solicitud';

    protected static ?string $pluralModelLabel = 'solicitudes de empleados';

    protected static string|UnitEnum|null $navigationGroup = 'hr';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Inbox;

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeRequestsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = EmployeeRequest::query()->where('status', EmployeeRequestStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Solicitudes por aprobar';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeRequests::route('/'),
            'view' => ViewEmployeeRequest::route('/{record}'),
        ];
    }

    public static function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
