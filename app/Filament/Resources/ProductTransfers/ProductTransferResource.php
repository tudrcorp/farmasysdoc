<?php

namespace App\Filament\Resources\ProductTransfers;

use App\Filament\Resources\ProductTransfers\Pages\CreateProductTransfer;
use App\Filament\Resources\ProductTransfers\Pages\EditProductTransfer;
use App\Filament\Resources\ProductTransfers\Pages\ListProductTransfers;
use App\Filament\Resources\ProductTransfers\Pages\ViewProductTransfer;
use App\Filament\Resources\ProductTransfers\Schemas\ProductTransferForm;
use App\Filament\Resources\ProductTransfers\Schemas\ProductTransferInfolist;
use App\Filament\Resources\ProductTransfers\Tables\ProductTransfersTable;
use App\Models\ProductTransfer;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductTransferResource extends Resource
{
    protected static ?string $model = ProductTransfer::class;

    protected static ?string $navigationLabel = 'Traslados';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowPath;

    public static function getNavigationGroup(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
    }

    public static function form(Schema $schema): Schema
    {
        return ProductTransferForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductTransferInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductTransfersTable::configure($table);
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
            'index' => ListProductTransfers::route('/'),
            'create' => CreateProductTransfer::route('/create'),
            'view' => ViewProductTransfer::route('/{record}'),
            'edit' => EditProductTransfer::route('/{record}/edit'),
        ];
    }
}
