<?php

namespace App\Filament\Resources\Rols\Pages;

use App\Filament\Resources\Rols\RolResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRols extends ListRecords
{
    protected static string $resource = RolResource::class;

    protected static ?string $title = 'Roles';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo rol'),
        ];
    }
}
