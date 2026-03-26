<?php

namespace App\Filament\Resources\Marketing\Broadcasts\Pages;

use App\Enums\MarketingBroadcastStatus;
use App\Filament\Resources\Marketing\Broadcasts\MarketingBroadcastResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketingBroadcast extends CreateRecord
{
    protected static string $resource = MarketingBroadcastResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->user()?->email ?? auth()->user()?->name ?? 'sistema';
        $data['status'] = $data['status'] ?? MarketingBroadcastStatus::Draft->value;

        return $data;
    }
}
