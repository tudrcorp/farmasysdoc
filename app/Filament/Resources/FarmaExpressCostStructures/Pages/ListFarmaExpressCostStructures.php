<?php

namespace App\Filament\Resources\FarmaExpressCostStructures\Pages;

use App\Filament\Resources\FarmaExpressCostStructures\FarmaExpressCostStructureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListFarmaExpressCostStructures extends ListRecords
{
    protected static string $resource = FarmaExpressCostStructureResource::class;

    protected static ?string $title = 'Estructura de Costos FarmaExpress';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva estructura')
                ->icon(Heroicon::Plus),
        ];
    }
}
