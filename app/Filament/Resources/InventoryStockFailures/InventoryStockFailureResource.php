<?php

namespace App\Filament\Resources\InventoryStockFailures;

use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Filament\Resources\InventoryStockFailures\Pages\ManageInventoryStockFailures;
use App\Filament\Resources\InventoryStockFailures\Pages\ViewInventoryStockFailure;
use App\Filament\Resources\InventoryStockFailures\Schemas\InventoryStockFailureInfolist;
use App\Filament\Resources\InventoryStockFailures\Tables\InventoryStockFailuresTable;
use App\Models\InventoryStockFailure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InventoryStockFailureResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = InventoryStockFailure::class;

    protected static ?string $navigationLabel = 'Fallas de existencia';

    protected static ?string $modelLabel = 'Falla de existencia';

    protected static ?string $pluralModelLabel = 'Fallas de existencia';

    protected static ?string $recordTitleAttribute = 'product_name';

    protected static ?int $navigationSort = 12;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ExclamationTriangle;

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function getNavigationBadge(): ?string
    {
        $todayCount = InventoryStockFailure::query()
            ->whereDate('created_at', today())
            ->count();

        return $todayCount > 0 ? (string) $todayCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
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

    public static function canView(Model $record): bool
    {
        if (! static::canViewAny()) {
            return false;
        }

        return static::getEloquentQuery()->whereKey($record->getKey())->exists();
    }

    /**
     * @return Builder<InventoryStockFailure>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['branch:id,name', 'product:id,name,barcode,sku', 'user:id,name,email']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryStockFailureInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryStockFailuresTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInventoryStockFailures::route('/'),
            'view' => ViewInventoryStockFailure::route('/{record}'),
        ];
    }
}
