<?php

namespace App\Filament\Resources\InventoryAdjustments;

use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Filament\Resources\InventoryAdjustments\Pages\ListInventoryAdjustments;
use App\Filament\Resources\InventoryAdjustments\Pages\ViewInventoryAdjustment;
use App\Filament\Resources\InventoryAdjustments\Schemas\InventoryAdjustmentInfolist;
use App\Filament\Resources\InventoryAdjustments\Tables\InventoryAdjustmentsTable;
use App\Models\InventoryAdjustment;
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

class InventoryAdjustmentResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = InventoryAdjustment::class;

    protected static ?string $navigationLabel = 'Ajustes de inventario';

    protected static ?string $modelLabel = 'Ajuste de inventario';

    protected static ?string $pluralModelLabel = 'Ajustes de inventario';

    protected static ?int $navigationSort = 22;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AdjustmentsHorizontal;

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function getEloquentQuery(): Builder
    {
        return BranchAuthScope::apply(parent::getEloquentQuery());
    }

    public static function canViewAny(): bool
    {
        $user = request()->user() ?? Auth::user();

        if (! $user instanceof User || (! $user->isAdministrator() && ! $user->hasGerenciaRole())) {
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryAdjustmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryAdjustmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryAdjustments::route('/'),
            'view' => ViewInventoryAdjustment::route('/{record}'),
        ];
    }
}
