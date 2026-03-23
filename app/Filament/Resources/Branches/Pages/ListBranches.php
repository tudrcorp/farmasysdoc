<?php

namespace App\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\BranchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListBranches extends ListRecords
{
    protected static string $resource = BranchResource::class;

    protected static ?string $title = 'Listado de Sucursales';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Sucursal')
                ->icon(Heroicon::Plus)
                ->color('primary'),
        ];
    }
}
