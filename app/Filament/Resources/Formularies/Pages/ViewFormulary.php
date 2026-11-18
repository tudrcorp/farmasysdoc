<?php

namespace App\Filament\Resources\Formularies\Pages;

use App\Filament\Resources\Formularies\Actions\DuplicateFormularyAction;
use App\Filament\Resources\Formularies\FormularyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFormulary extends ViewRecord
{
    protected static string $resource = FormularyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DuplicateFormularyAction::make(),
            EditAction::make(),
        ];
    }
}
