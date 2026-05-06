<?php

namespace App\Filament\Resources\AccountsReceivables;

use App\Filament\Resources\AccountsReceivables\Pages\ListAccountsReceivables;
use App\Filament\Resources\AccountsReceivables\Pages\ViewAccountsReceivable;
use App\Filament\Resources\AccountsReceivables\Schemas\AccountsReceivableInfolist;
use App\Filament\Resources\AccountsReceivables\Tables\AccountsReceivablesTable;
use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Models\AccountsReceivable;
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

class AccountsReceivableResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = AccountsReceivable::class;

    protected static ?string $navigationLabel = 'Cuentas por cobrar';

    protected static ?string $modelLabel = 'cuenta por cobrar';

    protected static ?string $pluralModelLabel = 'cuentas por cobrar';

    protected static ?int $navigationSort = 11;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowTrendingUp;

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
        return AccountsReceivableInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountsReceivablesTable::configure($table);
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
     * @param  Model|AccountsReceivable|null  $record
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if ($record instanceof AccountsReceivable) {
            return 'CxC · '.$record->sale_number_snapshot;
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
            'index' => ListAccountsReceivables::route('/'),
            'view' => ViewAccountsReceivable::route('/{record}'),
        ];
    }
}
