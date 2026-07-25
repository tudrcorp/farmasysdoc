<?php

namespace App\Filament\Resources\PurchaseBooks;

use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Filament\Resources\PurchaseBooks\Pages\ListPurchaseBooks;
use App\Filament\Resources\PurchaseBooks\Pages\ViewPurchaseBook;
use App\Filament\Resources\PurchaseBooks\Schemas\PurchaseBookInfolist;
use App\Filament\Resources\PurchaseBooks\Tables\PurchaseBooksTable;
use App\Models\PurchaseBook;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class PurchaseBookResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = PurchaseBook::class;

    protected static ?string $navigationLabel = 'Retenciones';

    protected static ?string $modelLabel = 'retención';

    protected static ?string $pluralModelLabel = 'retenciones';

    protected static ?int $navigationSort = 14;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ReceiptPercent;

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
        return PurchaseBookInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseBooksTable::configure($table);
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
     * @param  Model|PurchaseBook|null  $record
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if ($record instanceof PurchaseBook) {
            return 'Comprobante '.$record->voucher_number.' · '.$record->supplier_name;
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
            'index' => ListPurchaseBooks::route('/'),
            'view' => ViewPurchaseBook::route('/{record}'),
        ];
    }
}
