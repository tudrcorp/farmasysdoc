<?php

namespace App\Filament\Resources\ProductTransferSales;

use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use App\Filament\Resources\ProductTransferSales\Pages\CreateProductTransferSale;
use App\Filament\Resources\ProductTransferSales\Pages\ListProductTransferSales;
use App\Filament\Resources\ProductTransferSales\Pages\ViewProductTransferSale;
use App\Filament\Resources\ProductTransferSales\Schemas\ProductTransferSaleForm;
use App\Filament\Resources\ProductTransferSales\Schemas\ProductTransferSaleInfolist;
use App\Filament\Resources\ProductTransferSales\Tables\ProductTransferSalesTable;
use App\Models\ProductTransfer;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ProductTransferSaleResource extends Resource
{
    protected static ?string $model = ProductTransfer::class;

    protected static ?string $navigationLabel = 'Traslados de venta';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Truck;

    public static function getNavigationGroup(): ?string
    {
        $user = Auth::user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
    }

    public static function form(Schema $schema): Schema
    {
        return ProductTransferSaleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductTransferSaleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductTransferSalesTable::configure($table);
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
            'index' => ListProductTransferSales::route('/'),
            'create' => CreateProductTransferSale::route('/create'),
            'view' => ViewProductTransferSale::route('/{record}'),
        ];
    }

    /**
     * @return Builder<ProductTransfer>
     */
    public static function getEloquentQuery(): Builder
    {
        return ProductTransferResource::getEloquentQuery()
            ->where('transfer_type', 'sale_transfer');
    }

    public static function canView(Model $record): bool
    {
        if (! $record instanceof ProductTransfer) {
            return false;
        }

        return $record->transfer_type === 'sale_transfer'
            && ProductTransferResource::canView($record);
    }

    public static function canCreate(): bool
    {
        return ProductTransferResource::canCreate();
    }
}
