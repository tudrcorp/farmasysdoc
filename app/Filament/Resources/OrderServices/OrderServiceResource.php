<?php

namespace App\Filament\Resources\OrderServices;

use App\Filament\Resources\OrderServices\Pages\CreateOrderService;
use App\Filament\Resources\OrderServices\Pages\EditOrderService;
use App\Filament\Resources\OrderServices\Pages\ListOrderServices;
use App\Filament\Resources\OrderServices\Pages\ViewOrderService;
use App\Filament\Resources\OrderServices\Schemas\OrderServiceForm;
use App\Filament\Resources\OrderServices\Schemas\OrderServiceInfolist;
use App\Filament\Resources\OrderServices\Tables\OrderServicesTable;
use App\Models\OrderService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderServiceResource extends Resource
{
    protected static ?string $model = OrderService::class;

    protected static ?string $navigationLabel = 'Ordenes de servicio';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ListBullet;

    public static function getNavigationGroup(): ?string
    {
        return 'Aliados Comerciales';
    }

    public static function form(Schema $schema): Schema
    {
        return OrderServiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderServiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrderServicesTable::configure($table);
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
            'index' => ListOrderServices::route('/'),
            'create' => CreateOrderService::route('/create'),
            'view' => ViewOrderService::route('/{record}'),
            'edit' => EditOrderService::route('/{record}/edit'),
        ];
    }
}
