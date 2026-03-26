<?php

namespace App\Filament\Resources\Marketing\Broadcasts\Pages;

use App\Filament\Resources\Marketing\Broadcasts\MarketingBroadcastResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMarketingBroadcasts extends ListRecords
{
    protected static string $resource = MarketingBroadcastResource::class;

    protected static ?string $title = 'Difusiones masivas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva difusión')
                ->icon(Heroicon::Plus),
        ];
    }
}
