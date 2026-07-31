<?php

namespace App\Filament\Resources\InventoryAudits;

use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Filament\Resources\InventoryAudits\Pages\ListInventoryAudits;
use App\Filament\Resources\InventoryAudits\Pages\ViewInventoryAudit;
use App\Filament\Resources\InventoryAudits\Pages\WorkInventoryAudit;
use App\Filament\Resources\InventoryAudits\Schemas\InventoryAuditInfolist;
use App\Filament\Resources\InventoryAudits\Tables\InventoryAuditsTable;
use App\Models\InventoryAudit;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class InventoryAuditResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = InventoryAudit::class;

    protected static ?string $navigationLabel = 'Auditoría de inventario';

    protected static ?string $modelLabel = 'Auditoría de inventario';

    protected static ?string $pluralModelLabel = 'Auditorías de inventario';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 23;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentCheck;

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function getEloquentQuery(): Builder
    {
        return BranchAuthScope::apply(
            parent::getEloquentQuery()->with(['branch:id,name', 'startedBy:id,name', 'closedBy:id,name'])
        );
    }

    public static function canViewAny(): bool
    {
        $user = request()->user() ?? Auth::user();

        if (! $user instanceof User || (! $user->isAdministrator() && ! $user->isManager())) {
            return false;
        }

        if (FarmaadminDeliveryUserAccess::denies(static::class)) {
            return false;
        }

        if (! static::canAccessCurrentMenuItem()) {
            return false;
        }

        return static::getViewAnyAuthorizationResponse()->allowed();
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

    public static function canView(Model $record): bool
    {
        if (! static::canViewAny()) {
            return false;
        }

        return static::getEloquentQuery()->whereKey($record->getKey())->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryAuditInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryAuditsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryAudits::route('/'),
            'view' => ViewInventoryAudit::route('/{record}'),
            'work' => WorkInventoryAudit::route('/{record}/work'),
        ];
    }
}
