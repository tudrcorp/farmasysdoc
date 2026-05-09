<?php

namespace App\Filament\Resources\FarmaExpressCostStructures\Pages;

use App\Filament\Resources\FarmaExpressCostStructures\FarmaExpressCostStructureResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFarmaExpressCostStructure extends EditRecord
{
    protected static string $resource = FarmaExpressCostStructureResource::class;

    protected static ?string $title = 'Editar estructura de costos express';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
