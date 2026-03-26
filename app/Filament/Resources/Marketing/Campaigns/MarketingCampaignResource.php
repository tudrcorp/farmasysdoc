<?php

namespace App\Filament\Resources\Marketing\Campaigns;

use App\Filament\Resources\Marketing\Campaigns\Pages\CreateMarketingCampaign;
use App\Filament\Resources\Marketing\Campaigns\Pages\EditMarketingCampaign;
use App\Filament\Resources\Marketing\Campaigns\Pages\ListMarketingCampaigns;
use App\Filament\Resources\Marketing\Campaigns\Schemas\MarketingCampaignForm;
use App\Filament\Resources\Marketing\Campaigns\Tables\MarketingCampaignsTable;
use App\Filament\Resources\Marketing\Concerns\ChecksMarketingAccess;
use App\Models\MarketingCampaign;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MarketingCampaignResource extends Resource
{
    use ChecksMarketingAccess;

    protected static ?string $model = MarketingCampaign::class;

    protected static ?string $navigationLabel = 'Campañas';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Megaphone;

    public static function form(Schema $schema): Schema
    {
        return MarketingCampaignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingCampaignsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingCampaigns::route('/'),
            'create' => CreateMarketingCampaign::route('/create'),
            'edit' => EditMarketingCampaign::route('/{record}/edit'),
        ];
    }
}
