<?php

namespace App\Filament\Resources\PurchaseLedgers;

use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Filament\Resources\PurchaseLedgers\Pages\ListPurchaseLedgers;
use App\Filament\Resources\PurchaseLedgers\Pages\ViewPurchaseLedger;
use App\Filament\Resources\PurchaseLedgers\Schemas\PurchaseLedgerInfolist;
use App\Filament\Resources\PurchaseLedgers\Tables\PurchaseLedgersTable;
use App\Models\PurchaseLedger;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class PurchaseLedgerResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = PurchaseLedger::class;

    protected static ?string $navigationLabel = 'Libro de Compras';

    protected static ?string $modelLabel = 'registro del libro de compras';

    protected static ?string $pluralModelLabel = 'libro de compras';

    protected static ?int $navigationSort = 12;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BookOpen;

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
        return PurchaseLedgerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseLedgersTable::configure($table);
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
     * @param  Model|PurchaseLedger|null  $record
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if ($record instanceof PurchaseLedger) {
            $type = $record->document_type?->label() ?? 'Documento';

            return 'Op. '.$record->operation_number.' · '.$type.' · '.$record->document_number;
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
            'index' => ListPurchaseLedgers::route('/'),
            'view' => ViewPurchaseLedger::route('/{record}'),
        ];
    }
}
