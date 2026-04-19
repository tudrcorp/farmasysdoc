<?php

namespace App\Filament\Resources\Rols\Pages;

use App\Filament\Resources\Rols\RolResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRol extends ViewRecord
{
    protected static string $resource = RolResource::class;

    protected static ?string $title = 'Detalle del rol';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
