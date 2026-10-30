<?php

namespace App\Filament\Resources\Hr\Severance\Exits;

use App\Enums\EmployeeExitStatus;
use App\Filament\Clusters\Severance\SeveranceCluster;
use App\Filament\Resources\Hr\Severance\Exits\Pages\CreateEmployeeExit;
use App\Filament\Resources\Hr\Severance\Exits\Pages\ListEmployeeExits;
use App\Filament\Resources\Hr\Severance\Exits\Pages\ViewEmployeeExit;
use App\Filament\Resources\Hr\Severance\Exits\Schemas\EmployeeExitForm;
use App\Filament\Resources\Hr\Severance\Exits\Schemas\EmployeeExitInfolist;
use App\Filament\Resources\Hr\Severance\Exits\Tables\EmployeeExitsTable;
use App\Models\EmployeeExit;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
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

class EmployeeExitResource extends Resource
{
    protected static ?string $model = EmployeeExit::class;

    protected static ?string $cluster = SeveranceCluster::class;

    protected static ?string $slug = 'registro';

    protected static ?string $navigationLabel = 'Egresos';

    protected static ?string $modelLabel = 'egreso';

    protected static ?string $pluralModelLabel = 'egresos';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowRightStartOnRectangle;

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof EmployeeExit ? $record->employee->fullName() : parent::getRecordTitle($record);
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeeExitForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeExitInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeExitsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('employee.branch')
            ->whereHas('employee', fn (Builder $employees): Builder => BranchAuthScope::apply($employees));
    }

    public static function getNavigationBadge(): ?string
    {
        $open = static::getEloquentQuery()->where('status', EmployeeExitStatus::Open)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Egresos en proceso';
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
            && $user->canAccessEmployeeExits();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
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
            'index' => ListEmployeeExits::route('/'),
            'create' => CreateEmployeeExit::route('/create'),
            'view' => ViewEmployeeExit::route('/{record}'),
        ];
    }

    public static function currentUser(): ?User
    {
        $user = request()->user() ?? Auth::user();

        return $user instanceof User ? $user : null;
    }
}
