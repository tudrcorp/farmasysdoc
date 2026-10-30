<?php

namespace App\Filament\Resources\Hr\Severance\Settlements;

use App\Enums\EmployeeSettlementStatus;
use App\Filament\Clusters\Severance\SeveranceCluster;
use App\Filament\Resources\Hr\Severance\Settlements\Pages\ListEmployeeSettlements;
use App\Filament\Resources\Hr\Severance\Settlements\Pages\ViewEmployeeSettlement;
use App\Filament\Resources\Hr\Severance\Settlements\Schemas\EmployeeSettlementInfolist;
use App\Filament\Resources\Hr\Severance\Settlements\Tables\EmployeeSettlementsTable;
use App\Models\EmployeeSettlement;
use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EmployeeSettlementResource extends Resource
{
    protected static ?string $model = EmployeeSettlement::class;

    protected static ?string $cluster = SeveranceCluster::class;

    protected static ?string $slug = 'liquidaciones';

    protected static ?string $navigationLabel = 'Liquidaciones';

    protected static ?string $modelLabel = 'liquidación';

    protected static ?string $pluralModelLabel = 'liquidaciones';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Calculator;

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof EmployeeSettlement
            ? 'Liquidación de '.$record->employeeExit->employee->fullName()
            : parent::getRecordTitle($record);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeSettlementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeSettlementsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['employeeExit.employee.branch', 'lines']);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = EmployeeSettlement::query()->where('status', EmployeeSettlementStatus::PendingApproval)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Liquidaciones por aprobar';
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        $user = static::currentUser();

        return $user !== null
            && ! FarmaadminDeliveryUserAccess::denies(static::class)
            && $user->canAccessEmployeeSettlements();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
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
            'index' => ListEmployeeSettlements::route('/'),
            'view' => ViewEmployeeSettlement::route('/{record}'),
        ];
    }

    public static function currentUser(): ?User
    {
        $user = request()->user() ?? Auth::user();

        return $user instanceof User ? $user : null;
    }
}
