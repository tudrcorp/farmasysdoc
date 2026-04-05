<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = Order::class;

    protected static ?string $navigationLabel = 'Ordenes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ListBullet;

    public static function getNavigationGroup(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
    }

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        if ($user instanceof User && ! $user->isAdministrator() && $user->isPartnerCompanyUser()) {
            return $query->where('partner_company_id', (int) $user->partner_company_id);
        }

        return BranchAuthScope::apply($query);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return false;
        }

        return $user->isAdministrator() || filled($user->branch_id) || $user->isPartnerCompanyUser();
    }

    public static function canView(Model $record): bool
    {
        if (! $record instanceof Order) {
            return false;
        }

        $user = auth()->user();
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->isDeliveryUser()) {
            return false;
        }

        if ($user->isPartnerCompanyUser()) {
            return (int) $record->partner_company_id === (int) $user->partner_company_id;
        }

        if (! filled($user->branch_id)) {
            return false;
        }

        return $record->branch_id === null || (int) $record->branch_id === (int) $user->branch_id;
    }

    public static function canEdit(Model $record): bool
    {
        if (! static::canView($record) || ! $record instanceof Order) {
            return false;
        }

        $user = auth()->user();
        if ($user instanceof User && $user->isPartnerCompanyUser() && ! $user->isAdministrator()) {
            return $record->status === OrderStatus::Pending;
        }

        return true;
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
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'view' => ViewOrder::route('/{record}'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
