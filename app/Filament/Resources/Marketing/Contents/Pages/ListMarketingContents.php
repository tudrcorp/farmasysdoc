<?php

namespace App\Filament\Resources\Marketing\Contents\Pages;

use App\Filament\Resources\Marketing\Contents\MarketingContentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMarketingContents extends ListRecords
{
    protected static string $resource = MarketingContentResource::class;

    protected static ?string $title = 'Contenidos y promociones';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo contenido')
                ->icon(Heroicon::Plus),
        ];
    }
}
