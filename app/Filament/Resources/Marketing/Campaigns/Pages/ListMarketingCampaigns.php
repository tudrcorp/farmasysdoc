<?php

namespace App\Filament\Resources\Marketing\Campaigns\Pages;

use App\Filament\Resources\Marketing\Campaigns\MarketingCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMarketingCampaigns extends ListRecords
{
    protected static string $resource = MarketingCampaignResource::class;

    protected static ?string $title = 'Campañas de marketing';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva campaña')
                ->icon(Heroicon::Plus),
        ];
    }
}
