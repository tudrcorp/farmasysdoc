<?php

namespace App\Filament\BusinessPartners\Resources\OrderServices;

use App\Enums\PartnerPanelPermission;
use App\Filament\BusinessPartners\Resources\OrderServices\Pages\ListOrderServices;
use App\Filament\BusinessPartners\Resources\OrderServices\Pages\ViewOrderService;
use App\Filament\BusinessPartners\Resources\OrderServices\Schemas\OrderServiceInfolist;
use App\Filament\BusinessPartners\Resources\OrderServices\Tables\OrderServicesTable;
use App\Models\OrderService;
use App\Support\Partners\PartnerPanel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderServiceResource extends Resource
{
    protected static ?string $model = OrderService::class;

    protected static ?string $slug = 'ordenes-de-servicio';

    protected static ?string $modelLabel = 'orden de servicio';

    protected static ?string $pluralModelLabel = 'órdenes de servicio';

    protected static ?string $navigationLabel = 'Órdenes de servicio';

    protected static ?string $recordTitleAttribute = 'service_order_number';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ClipboardDocumentCheck;

    protected static ?int $navigationSort = 11;

    public static function getNavigationGroup(): ?string
    {
        return 'Operaciones';
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderServiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrderServicesTable::configure($table);
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
        return PartnerPanel::can(PartnerPanelPermission::ViewOrders);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny()
            && $record instanceof OrderService
            && (int) $record->partner_company_id === PartnerPanel::companyId();
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

    public static function getPages(): array
    {
        return [
            'index' => ListOrderServices::route('/'),
            'view' => ViewOrderService::route('/{record}'),
        ];
    }
}
