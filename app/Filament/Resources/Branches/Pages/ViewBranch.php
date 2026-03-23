<?php

namespace App\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\BranchResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewBranch extends ViewRecord
{
    protected static string $resource = BranchResource::class;

    protected static ?string $title = 'Detalle de la Sucursal';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar Sucursal')
                ->icon(Heroicon::Pencil)
                ->color('primary')
                ->size('sm'),
        ];
    }
}
