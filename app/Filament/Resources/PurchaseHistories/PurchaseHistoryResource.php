<?php

namespace App\Filament\Resources\PurchaseHistories;

use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Filament\Resources\PurchaseHistories\Pages\ListPurchaseHistories;
use App\Filament\Resources\PurchaseHistories\Pages\ViewPurchaseHistory;
use App\Filament\Resources\PurchaseHistories\Schemas\PurchaseHistoryInfolist;
use App\Filament\Resources\PurchaseHistories\Tables\PurchaseHistoriesTable;
use App\Models\PurchaseHistory;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PurchaseHistoryResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = PurchaseHistory::class;

    protected static ?string $navigationLabel = 'Histórico de compras';

    protected static ?string $modelLabel = 'movimiento de histórico';

    protected static ?string $pluralModelLabel = 'histórico de compras';

    protected static ?int $navigationSort = 13;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Clock;

    public static function getNavigationGroup(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseHistoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseHistoriesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return BranchAuthScope::apply(parent::getEloquentQuery());
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

    /**
     * @param  Model|PurchaseHistory|null  $record
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if ($record instanceof PurchaseHistory) {
            return 'Histórico #'.$record->getKey().' · '.$record->supplier_invoice_number;
        }

        return parent::getRecordTitle($record);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseHistories::route('/'),
            'view' => ViewPurchaseHistory::route('/{record}'),
        ];
    }
}
