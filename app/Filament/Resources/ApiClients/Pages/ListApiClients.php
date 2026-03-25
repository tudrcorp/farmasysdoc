<?php

namespace App\Filament\Resources\ApiClients\Pages;

use App\Filament\Resources\ApiClients\ApiClientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListApiClients extends ListRecords
{
    protected static string $resource = ApiClientResource::class;

    protected static ?string $title = 'Clientes API';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo cliente API')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
        ];
    }
}
