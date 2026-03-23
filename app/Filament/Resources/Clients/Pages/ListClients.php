<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected static ?string $title = 'Listado de Clientes';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Cliente')
                ->icon(Heroicon::Plus)
                ->color('primary'),
        ];
    }
}
