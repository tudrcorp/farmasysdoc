<?php

namespace App\Filament\Resources\InventoryAuditUpdates;

use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Filament\Resources\InventoryAuditUpdates\Pages\ListInventoryAuditUpdates;
use App\Filament\Resources\InventoryAuditUpdates\Tables\InventoryAuditUpdatesTable;
use App\Models\InventoryAuditUpdate;
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

class InventoryAuditUpdateResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = InventoryAuditUpdate::class;

    protected static ?string $navigationLabel = 'Productos actualizados (auditoría)';

    protected static ?string $modelLabel = 'Producto actualizado';

    protected static ?string $pluralModelLabel = 'Productos actualizados (auditoría)';

    protected static ?string $recordTitleAttribute = 'product_name';

    protected static ?int $navigationSort = 24;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentArrowDown;

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function getEloquentQuery(): Builder
    {
        return BranchAuthScope::apply(
            parent::getEloquentQuery()->with(['branch:id,name', 'processedBy:id,name'])
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
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return InventoryAuditUpdatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryAuditUpdates::route('/'),
        ];
    }
}
