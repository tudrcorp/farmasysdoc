<?php

namespace App\Filament\Resources\FarmaExpressCostStructures\Pages;

use App\Filament\Resources\FarmaExpressCostStructures\FarmaExpressCostStructureResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFarmaExpressCostStructure extends ViewRecord
{
    protected static string $resource = FarmaExpressCostStructureResource::class;

    protected static ?string $title = 'Detalle de estructura de costos express';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
