<?php

namespace App\Filament\Resources\FormularyCategories\Pages;

use App\Filament\Resources\FormularyCategories\FormularyCategoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFormularyCategory extends ViewRecord
{
    protected static string $resource = FormularyCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
