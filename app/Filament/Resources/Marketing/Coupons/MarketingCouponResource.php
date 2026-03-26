<?php

namespace App\Filament\Resources\Marketing\Coupons;

use App\Filament\Resources\Marketing\Concerns\ChecksMarketingAccess;
use App\Filament\Resources\Marketing\Coupons\Pages\CreateMarketingCoupon;
use App\Filament\Resources\Marketing\Coupons\Pages\EditMarketingCoupon;
use App\Filament\Resources\Marketing\Coupons\Pages\ListMarketingCoupons;
use App\Filament\Resources\Marketing\Coupons\Schemas\MarketingCouponForm;
use App\Filament\Resources\Marketing\Coupons\Tables\MarketingCouponsTable;
use App\Models\MarketingCoupon;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MarketingCouponResource extends Resource
{
    use ChecksMarketingAccess;

    protected static ?string $model = MarketingCoupon::class;

    protected static ?string $navigationLabel = 'Cupones';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 12;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Ticket;

    public static function form(Schema $schema): Schema
    {
        return MarketingCouponForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingCouponsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingCoupons::route('/'),
            'create' => CreateMarketingCoupon::route('/create'),
            'edit' => EditMarketingCoupon::route('/{record}/edit'),
        ];
    }
}
