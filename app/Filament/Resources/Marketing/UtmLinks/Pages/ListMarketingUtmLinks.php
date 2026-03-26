<?php

namespace App\Filament\Resources\Marketing\UtmLinks\Pages;

use App\Filament\Resources\Marketing\UtmLinks\MarketingUtmLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMarketingUtmLinks extends ListRecords
{
    protected static string $resource = MarketingUtmLinkResource::class;

    protected static ?string $title = 'Enlaces UTM';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo enlace')
                ->icon(Heroicon::Plus),
        ];
    }
}
