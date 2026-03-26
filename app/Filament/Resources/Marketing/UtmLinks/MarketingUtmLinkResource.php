<?php

namespace App\Filament\Resources\Marketing\UtmLinks;

use App\Filament\Resources\Marketing\Concerns\ChecksMarketingAccess;
use App\Filament\Resources\Marketing\UtmLinks\Pages\CreateMarketingUtmLink;
use App\Filament\Resources\Marketing\UtmLinks\Pages\EditMarketingUtmLink;
use App\Filament\Resources\Marketing\UtmLinks\Pages\ListMarketingUtmLinks;
use App\Filament\Resources\Marketing\UtmLinks\Schemas\MarketingUtmLinkForm;
use App\Filament\Resources\Marketing\UtmLinks\Tables\MarketingUtmLinksTable;
use App\Models\MarketingUtmLink;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MarketingUtmLinkResource extends Resource
{
    use ChecksMarketingAccess;

    protected static ?string $model = MarketingUtmLink::class;

    protected static ?string $navigationLabel = 'Enlaces UTM';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 15;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Link;

    public static function form(Schema $schema): Schema
    {
        return MarketingUtmLinkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingUtmLinksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingUtmLinks::route('/'),
            'create' => CreateMarketingUtmLink::route('/create'),
            'edit' => EditMarketingUtmLink::route('/{record}/edit'),
        ];
    }
}
