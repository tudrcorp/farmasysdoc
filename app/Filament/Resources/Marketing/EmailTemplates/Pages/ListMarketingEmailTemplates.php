<?php

namespace App\Filament\Resources\Marketing\EmailTemplates\Pages;

use App\Filament\Resources\Marketing\EmailTemplates\MarketingEmailTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMarketingEmailTemplates extends ListRecords
{
    protected static string $resource = MarketingEmailTemplateResource::class;

    protected static ?string $title = 'Plantillas de correo';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva plantilla')
                ->icon(Heroicon::Plus),
        ];
    }
}
