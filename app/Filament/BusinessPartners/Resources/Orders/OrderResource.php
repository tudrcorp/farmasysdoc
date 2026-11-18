<?php

namespace App\Filament\BusinessPartners\Resources\Orders;

use App\Enums\OrderStatus;
use App\Enums\PartnerPanelPermission;
use App\Filament\BusinessPartners\Resources\Orders\Pages\CreateOrder;
use App\Filament\BusinessPartners\Resources\Orders\Pages\EditOrder;
use App\Filament\BusinessPartners\Resources\Orders\Pages\ListOrders;
use App\Filament\BusinessPartners\Resources\Orders\Pages\ViewOrder;
use App\Filament\BusinessPartners\Resources\Orders\Schemas\OrderForm;
use App\Filament\BusinessPartners\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Support\Partners\PartnerPanel;
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ClipboardDocumentList;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return 'Operaciones';
    }

    public static function getNavigationBadge(): ?string
    {
        $partnerId = PartnerPanel::companyId();

        if ($partnerId === null) {
            return null;
        }

        $pending = Order::query()->where('partner_company_id', $partnerId)->where('status', OrderStatus::Pending->value)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pedidos pendientes';
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
        $partnerId = PartnerPanel::companyId();

        return $partnerId === null
            ? parent::getEloquentQuery()->whereRaw('1 = 0')
            : parent::getEloquentQuery()->where('partner_company_id', $partnerId);
    }

    public static function canViewAny(): bool
    {
        return PartnerPanel::canAny(PartnerPanelPermission::ViewOrders, PartnerPanelPermission::CreateOrders);
    }

    public static function canCreate(): bool
    {
        return PartnerPanel::can(PartnerPanelPermission::CreateOrders);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny()
            && $record instanceof Order
            && (int) $record->partner_company_id === PartnerPanel::companyId();
    }

    /**
     * Solo los pedidos pendientes se modifican o eliminan, y solo con «Crear pedidos».
     */
    public static function canEdit(Model $record): bool
    {
        return static::canView($record)
            && static::canCreate()
            && $record instanceof Order
            && $record->status === OrderStatus::Pending;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function canDeleteAny(): bool
    {
        return false;
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
