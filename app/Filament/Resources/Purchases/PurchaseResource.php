<?php

namespace App\Filament\Resources\Purchases;

use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Purchases\Pages\EditPurchase;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Resources\Purchases\Schemas\PurchaseForm;
use App\Filament\Resources\Purchases\Tables\PurchasesTable;
use App\Models\Purchase;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = Purchase::class;

    protected static ?string $navigationLabel = 'Compras';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShoppingCart;

    public static function getNavigationGroup(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchasesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return BranchAuthScope::apply(parent::getEloquentQuery());
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
            'index' => ListPurchases::route('/'),
            'create' => CreatePurchase::route('/create'),
            'edit' => EditPurchase::route('/{record}/edit'),
        ];
    }
}
