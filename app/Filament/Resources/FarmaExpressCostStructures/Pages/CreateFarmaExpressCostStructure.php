<?php

namespace App\Filament\Resources\FarmaExpressCostStructures\Pages;

use App\Filament\Resources\FarmaExpressCostStructures\FarmaExpressCostStructureResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFarmaExpressCostStructure extends CreateRecord
{
    protected static string $resource = FarmaExpressCostStructureResource::class;

    protected static ?string $title = 'Nueva estructura de costos express';
}
