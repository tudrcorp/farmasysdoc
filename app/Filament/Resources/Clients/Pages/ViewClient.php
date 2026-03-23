<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected static ?string $title = 'Detalle del Cliente';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar Cliente')
                ->icon(Heroicon::Pencil)
                ->color('primary')
                ->size('sm'),
        ];
    }
}
