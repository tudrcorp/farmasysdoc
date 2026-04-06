<?php

namespace App\Filament\BusinessPartners\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\BusinessPartners\Resources\Orders\Pages\CreateOrder;
use App\Filament\BusinessPartners\Resources\Orders\Pages\EditOrder;
use App\Filament\BusinessPartners\Resources\Orders\Pages\ListOrders;
use App\Filament\BusinessPartners\Resources\Orders\Pages\ViewOrder;
use App\Filament\BusinessPartners\Resources\Orders\Schemas\OrderForm;
use App\Filament\BusinessPartners\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationLabel = 'Pedidos';

    protected static ?string $modelLabel = 'pedido';

    protected static ?string $pluralModelLabel = 'pedidos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ListBullet;

    public static function getNavigationGroup(): ?string
    {
        return 'Operaciones';
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
        return OrdersTable::configure(
            $table,
            urlResource: self::class,
            includeBranchAndClientFilters: false,
            partnerOrderNumberDeliveryModal: true,
        );
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        if ($user instanceof User && $user->isPartnerCompanyUser()) {
            return $query->where('partner_company_id', (int) $user->partner_company_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return false;
        }

        return $user->isPartnerCompanyUser();
    }

    public static function canView(Model $record): bool
    {
        if (! $record instanceof Order) {
            return false;
        }

        $user = auth()->user();
        if (! $user instanceof User || ! $user->isPartnerCompanyUser()) {
            return false;
        }

        return (int) $record->partner_company_id === (int) $user->partner_company_id;
    }

    public static function canEdit(Model $record): bool
    {
        if (! static::canView($record) || ! $record instanceof Order) {
            return false;
        }

        return $record->status === OrderStatus::Pending;
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
