<?php

namespace App\Filament\Resources\Marketing\Broadcasts;

use App\Filament\Resources\Marketing\Broadcasts\Pages\CreateMarketingBroadcast;
use App\Filament\Resources\Marketing\Broadcasts\Pages\EditMarketingBroadcast;
use App\Filament\Resources\Marketing\Broadcasts\Pages\ListMarketingBroadcasts;
use App\Filament\Resources\Marketing\Broadcasts\Pages\ViewMarketingBroadcast;
use App\Filament\Resources\Marketing\Broadcasts\Schemas\MarketingBroadcastForm;
use App\Filament\Resources\Marketing\Broadcasts\Schemas\MarketingBroadcastInfolist;
use App\Filament\Resources\Marketing\Broadcasts\Tables\MarketingBroadcastsTable;
use App\Filament\Resources\Marketing\Concerns\ChecksMarketingAccess;
use App\Models\MarketingBroadcast;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MarketingBroadcastResource extends Resource
{
    use ChecksMarketingAccess;

    protected static ?string $model = MarketingBroadcast::class;

    protected static ?string $navigationLabel = 'Difusiones';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 11;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::PaperAirplane;

    public static function form(Schema $schema): Schema
    {
        return MarketingBroadcastForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MarketingBroadcastInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingBroadcastsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingBroadcasts::route('/'),
            'create' => CreateMarketingBroadcast::route('/create'),
            'view' => ViewMarketingBroadcast::route('/{record}'),
            'edit' => EditMarketingBroadcast::route('/{record}/edit'),
        ];
    }
}
