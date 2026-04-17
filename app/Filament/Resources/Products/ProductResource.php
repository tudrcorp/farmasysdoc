<?php

namespace App\Filament\Resources\Products;

use App\Filament\GlobalSearch\FarmaadminGlobalSearchProvider;
use App\Filament\Resources\Concerns\ChecksConfigurationAccess;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Schemas\ProductInfolist;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Product;
use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use UnitEnum;

class ProductResource extends Resource
{
    use ChecksConfigurationAccess;

    protected static ?string $model = Product::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Productos';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cube;

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    /**
     * Ver ficha (p. ej. desde búsqueda global): administrador o quien opera ventas/compras. El listado sigue requiriendo {@see canViewAny()}.
     */
    public static function canView(Model $record): bool
    {
        if (FarmaadminDeliveryUserAccess::denies(static::class)) {
            return false;
        }

        $user = auth()->user();

        if ($user instanceof User && $user->isAdministrator()) {
            return true;
        }

        return SaleResource::canAccess() || PurchaseResource::canAccess();
    }

    /**
     * La búsqueda global de productos la resuelve {@see FarmaadminGlobalSearchProvider}.
     */
    public static function getGlobalSearchResults(string $search): Collection
    {
        return collect();
    }
}
