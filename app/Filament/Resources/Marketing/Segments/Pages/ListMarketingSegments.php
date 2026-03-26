<?php

namespace App\Filament\Resources\Marketing\Segments\Pages;

use App\Filament\Resources\Marketing\Segments\MarketingSegmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMarketingSegments extends ListRecords
{
    protected static string $resource = MarketingSegmentResource::class;

    protected static ?string $title = 'Segmentos de clientes';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo segmento')
                ->icon(Heroicon::Plus),
        ];
    }
}
