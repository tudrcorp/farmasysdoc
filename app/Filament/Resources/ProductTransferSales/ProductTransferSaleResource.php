<?php

namespace App\Filament\Resources\ProductTransferSales;

use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use App\Filament\Resources\ProductTransferSales\Pages\CreateProductTransferSale;
use App\Filament\Resources\ProductTransferSales\Pages\ListProductTransferSales;
use App\Filament\Resources\ProductTransferSales\Pages\ViewProductTransferSale;
use App\Filament\Resources\ProductTransferSales\Schemas\ProductTransferSaleForm;
use App\Filament\Resources\ProductTransferSales\Schemas\ProductTransferSaleInfolist;
use App\Filament\Resources\ProductTransferSales\Tables\ProductTransferSalesTable;
use App\Models\ProductTransfer;
use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
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
    use RestrictsAccessForDeliveryUsers;

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
     * Listado: traslados de venta donde la sucursal del usuario es origen o destino.
     *
     * Administrador y delivery: todos. GERENCIA: origen o destino en sucursales del pivote.
     * Resto (p. ej. cajero): traslados emitidos desde su sucursal o recibidos en ella
     * (incluye «En proceso» hacia su sucursal para completar la venta en caja).
     *
     * @return Builder<ProductTransfer>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('transfer_type', 'sale_transfer');

        $user = Auth::user();
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdministrator() || $user->isDeliveryUser()) {
            return $query;
        }

        $branchIds = $user->restrictedBranchIdsForQueries();
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($branchIds): void {
            $q->whereIn('to_branch_id', $branchIds)
                ->orWhereIn('from_branch_id', $branchIds);
        });
    }

    public static function canView(Model $record): bool
    {
        if (! $record instanceof ProductTransfer) {
            return false;
        }

        if ($record->transfer_type !== 'sale_transfer') {
            return false;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdministrator() || $user->isDeliveryUser()) {
            return true;
        }

        $branchIds = $user->restrictedBranchIdsForQueries();
        if ($branchIds === []) {
            return false;
        }

        $toBranchId = (int) $record->to_branch_id;
        $fromBranchId = (int) $record->from_branch_id;

        return in_array($toBranchId, $branchIds, true)
            || in_array($fromBranchId, $branchIds, true);
    }

    public static function canCreate(): bool
    {
        if (FarmaadminDeliveryUserAccess::denies(static::class)) {
            return false;
        }

        if (! static::canAccessCurrentMenuItem()) {
            return false;
        }

        return ProductTransferResource::canCreate();
    }

    public static function canDelete(Model $record): bool
    {
        return ProductTransferResource::canDelete($record);
    }
}
