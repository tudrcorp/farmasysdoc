<?php

namespace App\Filament\Resources\Marketing\Campaigns\Pages;

use App\Filament\Resources\Marketing\Campaigns\MarketingCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketingCampaign extends CreateRecord
{
    protected static string $resource = MarketingCampaignResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->user()?->email ?? auth()->user()?->name ?? 'sistema';

        return $data;
    }
}
