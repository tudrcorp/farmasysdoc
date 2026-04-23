<?php

namespace App\Filament\Resources\AccountsPayables;

use App\Filament\Resources\AccountsPayables\Pages\ListAccountsPayables;
use App\Filament\Resources\AccountsPayables\Pages\ViewAccountsPayable;
use App\Filament\Resources\AccountsPayables\Schemas\AccountsPayableInfolist;
use App\Filament\Resources\AccountsPayables\Tables\AccountsPayablesTable;
use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Models\AccountsPayable;
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

class AccountsPayableResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = AccountsPayable::class;

    protected static ?string $navigationLabel = 'Cuentas por pagar';

    protected static ?string $modelLabel = 'cuenta por pagar';

    protected static ?string $pluralModelLabel = 'cuentas por pagar';

    protected static ?int $navigationSort = 12;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

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
        return AccountsPayableInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountsPayablesTable::configure($table);
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
     * @param  Model|AccountsPayable|null  $record
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if ($record instanceof AccountsPayable) {
            return 'CxP · '.$record->supplier_invoice_number;
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
            'index' => ListAccountsPayables::route('/'),
            'view' => ViewAccountsPayable::route('/{record}'),
        ];
    }
}
